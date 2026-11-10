<?php
declare(strict_types=1);

namespace Pfdk\Remote;

use Pfdk\Config\DeployConfig;

final class CurlRemoteFiles implements RemoteFiles
{
    /** PHP exposes no constants for these: FTP couldn't retrieve file (19), file couldn't be read (37), remote file not found (78). */
    private const NOT_FOUND_ERRORS = [19, 37, 78];

    /**
     * Couldn't connect (7), FTP accept failed (10), timed out (28), send (55) and receive (56) errors in the middle of a
     * transfer. Only downloads and the upload of the temporary file repeat these, because repeating them is harmless.
     * Login (67, 9) and not-found errors never repeat.
     */
    private const RETRYABLE_ERRORS = [7, 10, 28, 55, 56];

    private const PAUSE_SECONDS = 3;

    private const UPLOAD_SUFFIX = '.pfdk-upload';

    /** curl's "quote command failed" (21): the server answered the rename or delete with an FTP error (5xx). */
    private const REFUSED = 21;

    /** @var callable(string, array<int,mixed>):array{ok:bool,errno:int,error:string,size?:int} */
    private $executor;

    /** @var callable(int):void */
    private $sleep;

    /** @var (callable(string):void)|null */
    private $notify;

    /**
     * @param (callable(string, array<int,mixed>):array{ok:bool,errno:int,error:string,size?:int})|null $executor test seam for the curl call
     * @param (callable(int):void)|null                                                       $sleep    test seam for the pause between attempts
     * @param (callable(string):void)|null                                                    $notify   receives the retry messages
     */
    public function __construct(
        private readonly DeployConfig $config,
        ?callable $executor = null,
        ?callable $sleep = null,
        ?callable $notify = null,
    ) {
        $this->executor = $executor ?? $this->curlExecutor(...);
        $this->sleep = $sleep ?? static function (int $seconds): void {
            sleep($seconds);
        };
        $this->notify = $notify;
    }

    public function download(string $remotePath, string $localFile): bool
    {
        is_dir(dirname($localFile)) || mkdir(dirname($localFile), 0777, true);

        $result = $this->withRetries(function () use ($remotePath, $localFile): array {
            // Each attempt starts a fresh file, so a half-written download is never reused.
            $handle = fopen($localFile, 'wb');
            $result = ($this->executor)($this->url($remotePath), [CURLOPT_FILE => $handle]);
            fclose($handle);

            return $result;
        });

        $notFound = in_array($result['errno'], self::NOT_FOUND_ERRORS, true);
        if (!$result['ok'] && !$notFound) {
            @unlink($localFile);

            throw new \RuntimeException("Download failed{$this->attemptsNote($result)}: {$result['error']}");
        }
        if (!$result['ok'] || filesize($localFile) === 0) {
            @unlink($localFile);

            return false;
        }

        return true;
    }

    /**
     * The live file is only replaced by a complete upload: it goes to a temporary name next to it, its size is
     * checked, and then it is renamed. Only the transfer itself is repeated on connection errors; the checks, the
     * rename and every delete run once, because repeating them after a lost answer could destroy the live file.
     */
    public function upload(string $localFile, string $remotePath): void
    {
        $temporary = $remotePath . self::UPLOAD_SUFFIX;
        $size = filesize($localFile);

        if ($this->config->scheme === 'file') {
            $this->uploadToFolder($localFile, $remotePath, $temporary);

            return;
        }

        try {
            $this->transferTemporary($localFile, $temporary);
            $this->assertSize($temporary, $size);
        } catch (\RuntimeException $e) {
            $this->removeTemporary($temporary);

            throw $e;
        }

        $this->replace($temporary, $remotePath, $size);
    }

    private function transferTemporary(string $localFile, string $temporary): void
    {
        $result = $this->withRetries(function () use ($localFile, $temporary): array {
            $handle = fopen($localFile, 'rb');
            $result = ($this->executor)($this->url($temporary), [
                CURLOPT_UPLOAD => true,
                CURLOPT_INFILE => $handle,
                CURLOPT_INFILESIZE => filesize($localFile),
                CURLOPT_FTP_CREATE_MISSING_DIRS => true,
            ]);
            fclose($handle);

            return $result;
        });

        if (!$result['ok']) {
            throw new \RuntimeException("Upload failed{$this->attemptsNote($result)}: {$result['error']}");
        }
    }

    private function assertSize(string $relative, int $expected): void
    {
        $result = $this->remoteSize($relative);
        if (!$result['ok']) {
            throw new \RuntimeException("Upload failed (could not check the size): {$result['error']}");
        }
        if ($result['size'] < 0) {
            throw new \RuntimeException('Server reports no file size, so the upload cannot be verified and is not used; the live file was not touched.');
        }
        if ($result['size'] !== $expected) {
            throw new \RuntimeException(sprintf('Upload incomplete: %d of %d bytes arrived; the live file was not touched.', $result['size'], $expected));
        }
    }

    /**
     * Renames over the target. The server's answer to a rename can get lost after it was carried out, so after a
     * connection error the state is read back instead of guessed. Deleting the target is only a fallback for a server
     * that explicitly refuses to rename over an existing file (the order dg uses), never a reaction to a network error.
     */
    private function replace(string $temporary, string $target, int $size): void
    {
        $first = $this->rename($temporary, $target);
        if ($first['ok']) {
            return;
        }

        if (in_array($first['errno'], self::RETRYABLE_ERRORS, true)) {
            $this->settleAfterLostAnswer($temporary, $target, $size, $first['error']);

            return;
        }

        if ($first['errno'] !== self::REFUSED) {
            $this->removeTemporary($temporary);

            throw new \RuntimeException("Upload failed while renaming: {$first['error']}. The live file was not touched.");
        }

        $this->overwriteAfterRefusal($temporary, $target, $size, $first['error']);
    }

    private function settleAfterLostAnswer(string $temporary, string $target, int $size, string $error): void
    {
        $targetSize = $this->remoteSize($target);
        $temporarySize = $this->remoteSize($temporary);
        $temporaryGone = !$temporarySize['ok'] && in_array($temporarySize['errno'], self::NOT_FOUND_ERRORS, true);

        if ($targetSize['ok'] && $targetSize['size'] === $size && $temporaryGone) {
            return;
        }

        if ($temporarySize['ok'] && $temporarySize['size'] === $size) {
            $this->removeTemporary($temporary);

            throw new \RuntimeException("Upload failed while renaming ($error). The rename did not happen, the live file was not touched and the temporary file was removed.");
        }

        [$target, $temporary] = [$this->serverPath($target), $this->serverPath($temporary)];

        throw new \RuntimeException("Upload state unclear after a connection error while renaming ($error). Nothing was deleted. Check $target and $temporary on the server: if $temporary is gone and $target is the new file, the upload worked; if both exist, $target is still the old file and $temporary can be deleted.");
    }

    private function overwriteAfterRefusal(string $temporary, string $target, int $size, string $error): void
    {
        $temporarySize = $this->remoteSize($temporary);
        if (!$temporarySize['ok'] || $temporarySize['size'] !== $size) {
            $this->removeTemporary($temporary);

            throw new \RuntimeException("Upload failed while renaming: $error. The live file was not touched.");
        }

        $deleted = $this->once($this->url(''), [CURLOPT_NOBODY => true, CURLOPT_QUOTE => ['DELE ' . $this->serverPath($target)]]);
        if (!$deleted['ok']) {
            if ($deleted['errno'] === self::REFUSED) {
                $this->removeTemporary($temporary);

                throw new \RuntimeException("Upload failed: the server refuses to replace $target ({$deleted['error']}). The live file was not touched.");
            }

            [$target, $temporary] = [$this->serverPath($target), $this->serverPath($temporary)];

            throw new \RuntimeException("Upload state unclear after a connection error while deleting $target ({$deleted['error']}). Check $target and $temporary on the server; $temporary holds the complete new file.");
        }

        $second = $this->rename($temporary, $target);
        if ($second['ok']) {
            return;
        }

        $remaining = $this->remoteSize($temporary);
        [$target, $temporary] = [$this->serverPath($target), $this->serverPath($temporary)];
        if ($remaining['ok'] && $remaining['size'] === $size) {
            throw new \RuntimeException("Upload failed while renaming: {$second['error']}. The old live file was removed; the complete new file is on the server as $temporary.");
        }

        throw new \RuntimeException("Upload state unclear after the old live file was removed ({$second['error']}). Check $target and $temporary on the server.");
    }

    /** @return array{ok:bool,errno:int,error:string} */
    private function rename(string $from, string $to): array
    {
        return $this->once($this->url(''), [CURLOPT_NOBODY => true, CURLOPT_QUOTE => ['RNFR ' . $this->serverPath($from), 'RNTO ' . $this->serverPath($to)]]);
    }

    /** @return array{ok:bool,errno:int,error:string,size:int} */
    private function remoteSize(string $relative): array
    {
        return $this->once($this->url($relative), [CURLOPT_NOBODY => true]) + ['size' => -1];
    }

    private function removeTemporary(string $relative): void
    {
        $this->once($this->url(''), [CURLOPT_NOBODY => true, CURLOPT_QUOTE => ['DELE ' . $this->serverPath($relative)]]);
    }

    /**
     * @param array<int,mixed> $options
     *
     * @return array{ok:bool,errno:int,error:string,size?:int}
     */
    private function once(string $url, array $options): array
    {
        return ($this->executor)($url, $options);
    }

    private function serverPath(string $relative): string
    {
        $path = ($this->config->remotePath !== '' ? $this->config->remotePath . '/' : '') . ltrim($relative, '/');
        if (preg_match('/[\r\n]/', $path) === 1) {
            throw new \RuntimeException('Remote path contains a line break.');
        }

        return $path;
    }

    private function uploadToFolder(string $localFile, string $remotePath, string $temporary): void
    {
        $target = $this->folderPath($remotePath);
        $partial = $this->folderPath($temporary);

        if (!@copy($localFile, $partial) || filesize($partial) !== filesize($localFile)) {
            @unlink($partial);

            throw new \RuntimeException("Upload failed: cannot write $remotePath");
        }
        if (!@rename($partial, $target)) {
            @unlink($partial);

            throw new \RuntimeException("Upload failed: cannot replace $remotePath; the old file was not touched.");
        }
    }

    /** From the configured path, not from the URL, so #, ? and % in a name stay what they are. */
    private function folderPath(string $relative): string
    {
        $base = rawurldecode($this->config->remotePath);

        return '/' . ($base !== '' ? $base . '/' : '') . ltrim($relative, '/');
    }

    /** dg's ftps:// is explicit TLS on the normal port; curl's own ftps:// would mean implicit TLS on port 990. */
    public function url(string $remotePath): string
    {
        return preg_replace('~^ftps://~', 'ftp://', $this->config->remoteUrl($remotePath));
    }

    /**
     * @param callable():array{ok:bool,errno:int,error:string} $attempt
     *
     * @return array{ok:bool,errno:int,error:string,attempts?:int}
     */
    private function withRetries(callable $attempt): array
    {
        for ($try = 1;; ++$try) {
            $result = $attempt();
            if ($result['ok'] || !in_array($result['errno'], self::RETRYABLE_ERRORS, true) || $try >= $this->config->retries) {
                return $result + ['attempts' => $try];
            }

            $this->notify === null || ($this->notify)(sprintf('Connection failed or timed out, retrying (%d/%d) ...', $try + 1, $this->config->retries));
            ($this->sleep)(self::PAUSE_SECONDS);
        }
    }

    /** @param array{attempts?:int} $result */
    private function attemptsNote(array $result): string
    {
        $attempts = $result['attempts'] ?? 1;

        return $attempts > 1 ? " after $attempts attempts" : '';
    }

    /**
     * @param array<int,mixed> $options
     *
     * @return array{ok:bool,errno:int,error:string,size:int}
     */
    private function curlExecutor(string $url, array $options): array
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_USERPWD => $this->config->user . ':' . $this->config->password,
            CURLOPT_FAILONERROR => true,
            CURLOPT_CONNECTTIMEOUT => 20,
        ] + $options);
        if ($this->config->scheme === 'ftps') {
            curl_setopt($curl, CURLOPT_USE_SSL, CURLUSESSL_ALL);
        }
        $ok = curl_exec($curl) !== false;
        $result = ['ok' => $ok, 'errno' => curl_errno($curl), 'error' => curl_error($curl), 'size' => (int) curl_getinfo($curl, CURLINFO_CONTENT_LENGTH_DOWNLOAD_T)];
        curl_close($curl);

        return $result;
    }
}
