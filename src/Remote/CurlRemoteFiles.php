<?php
declare(strict_types=1);

namespace Pfdk\Remote;

use Pfdk\Config\DeployConfig;

final class CurlRemoteFiles implements RemoteFiles
{
    /** PHP exposes no constants for these: FTP couldn't retrieve file (19), file couldn't be read (37), remote file not found (78). */
    private const NOT_FOUND_ERRORS = [19, 37, 78];

    /** Couldn't connect (7), FTP accept failed (10), operation timed out (28). Login (67, 9) and not-found errors never repeat. */
    private const RETRYABLE_ERRORS = [7, 10, 28];

    private const PAUSE_SECONDS = 3;

    /** @var callable(string, array<int,mixed>):array{ok:bool,errno:int,error:string} */
    private $executor;

    /** @var callable(int):void */
    private $sleep;

    /** @var (callable(string):void)|null */
    private $notify;

    /**
     * @param (callable(string, array<int,mixed>):array{ok:bool,errno:int,error:string})|null $executor test seam for the curl call
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

    public function upload(string $localFile, string $remotePath): void
    {
        $result = $this->withRetries(function () use ($localFile, $remotePath): array {
            $handle = fopen($localFile, 'rb');
            $result = ($this->executor)($this->url($remotePath), [
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

            $this->notify === null || ($this->notify)(sprintf('Connection failed, retrying (%d/%d) ...', $try + 1, $this->config->retries));
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
     * @return array{ok:bool,errno:int,error:string}
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
        $result = ['ok' => $ok, 'errno' => curl_errno($curl), 'error' => curl_error($curl)];
        curl_close($curl);

        return $result;
    }
}
