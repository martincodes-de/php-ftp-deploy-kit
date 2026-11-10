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
     * checked, and then it is renamed. Any failure removes the temporary file.
     */
    public function upload(string $localFile, string $remotePath): void
    {
        $temporary = $remotePath . self::UPLOAD_SUFFIX;

        if ($this->config->scheme === 'file') {
            $this->uploadToFolder($localFile, $remotePath, $temporary);

            return;
        }

        try {
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
            $this->assertOk($result, 'Upload failed');

            $size = $this->call($this->url($temporary), [CURLOPT_NOBODY => true]);
            $this->assertOk($size, 'Upload failed (could not check the size)');
            if (($size['size'] ?? -1) !== filesize($localFile)) {
                throw new \RuntimeException(sprintf('Upload incomplete: %d of %d bytes arrived; the live file was not touched.', max(0, $size['size'] ?? 0), filesize($localFile)));
            }
        } catch (\RuntimeException $e) {
            $this->removeRemote($temporary);

            throw $e;
        }

        $this->replace($temporary, $remotePath);
    }

    /** Same order as dg: a server that refuses to rename over an existing file gets the target deleted first. */
    private function replace(string $temporary, string $target): void
    {
        $rename = [CURLOPT_NOBODY => true, CURLOPT_QUOTE => ['RNFR ' . $this->serverPath($temporary), 'RNTO ' . $this->serverPath($target)]];
        $first = $this->call($this->url(''), $rename);
        if ($first['ok']) {
            return;
        }

        $deleted = $this->call($this->url(''), [CURLOPT_NOBODY => true, CURLOPT_QUOTE => ['DELE ' . $this->serverPath($target)]]);
        $second = $this->call($this->url(''), $rename);
        if ($second['ok']) {
            return;
        }

        if ($deleted['ok']) {
            throw new \RuntimeException("Upload failed while renaming: {$second['error']}. The old live file was removed; the complete new file is on the server as $temporary.");
        }
        $this->removeRemote($temporary);

        throw new \RuntimeException("Upload failed while renaming: {$second['error']}. The live file was not touched.");
    }

    private function removeRemote(string $relative): void
    {
        $this->call($this->url(''), [CURLOPT_NOBODY => true, CURLOPT_QUOTE => ['DELE ' . $this->serverPath($relative)]]);
    }

    private function serverPath(string $relative): string
    {
        return ($this->config->remotePath !== '' ? $this->config->remotePath . '/' : '') . ltrim($relative, '/');
    }

    private function uploadToFolder(string $localFile, string $remotePath, string $temporary): void
    {
        $target = (string) parse_url($this->url($remotePath), PHP_URL_PATH);
        $partial = (string) parse_url($this->url($temporary), PHP_URL_PATH);

        if (!@copy($localFile, $partial)) {
            throw new \RuntimeException("Upload failed: cannot write $remotePath");
        }
        if (filesize($partial) !== filesize($localFile) || !@rename($partial, $target)) {
            @unlink($partial);

            throw new \RuntimeException("Upload failed: cannot replace $remotePath; the old file was not touched.");
        }
    }

    /** @param array{ok:bool,errno:int,error:string,attempts?:int} $result */
    private function assertOk(array $result, string $message): void
    {
        if (!$result['ok']) {
            throw new \RuntimeException("$message{$this->attemptsNote($result)}: {$result['error']}");
        }
    }

    /**
     * @param array<int,mixed> $options
     *
     * @return array{ok:bool,errno:int,error:string,size?:int,attempts?:int}
     */
    private function call(string $url, array $options): array
    {
        return $this->withRetries(fn (): array => ($this->executor)($url, $options));
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
