<?php
declare(strict_types=1);

namespace Pfdk\Remote;

use Pfdk\Config\DeployConfig;

final class CurlRemoteFiles implements RemoteFiles
{
    /** PHP exposes no constants for these: FTP couldn't retrieve file (19), file couldn't be read (37), remote file not found (78). */
    private const NOT_FOUND_ERRORS = [19, 37, 78];

    public function __construct(private readonly DeployConfig $config)
    {
    }

    public function download(string $remotePath, string $localFile): bool
    {
        is_dir(dirname($localFile)) || mkdir(dirname($localFile), 0777, true);
        $handle = fopen($localFile, 'wb');
        $curl = $this->curl($remotePath);
        curl_setopt($curl, CURLOPT_FILE, $handle);
        $ok = curl_exec($curl) !== false;
        $errno = curl_errno($curl);
        $error = curl_error($curl);
        curl_close($curl);
        fclose($handle);

        $notFound = in_array($errno, self::NOT_FOUND_ERRORS, true);
        if (!$ok && !$notFound) {
            @unlink($localFile);

            throw new \RuntimeException("Download failed: $error");
        }
        if (!$ok || filesize($localFile) === 0) {
            @unlink($localFile);

            return false;
        }

        return true;
    }

    public function upload(string $localFile, string $remotePath): void
    {
        $handle = fopen($localFile, 'rb');
        $curl = $this->curl($remotePath);
        curl_setopt_array($curl, [
            CURLOPT_UPLOAD => true,
            CURLOPT_INFILE => $handle,
            CURLOPT_INFILESIZE => filesize($localFile),
            CURLOPT_FTP_CREATE_MISSING_DIRS => true,
        ]);
        $ok = curl_exec($curl) !== false;
        $error = curl_error($curl);
        curl_close($curl);
        fclose($handle);

        if (!$ok) {
            throw new \RuntimeException("Upload failed: $error");
        }
    }

    /** dg's ftps:// is explicit TLS on the normal port; curl's own ftps:// would mean implicit TLS on port 990. */
    public function url(string $remotePath): string
    {
        return preg_replace('~^ftps://~', 'ftp://', $this->config->remoteUrl($remotePath));
    }

    private function curl(string $remotePath): \CurlHandle
    {
        $curl = curl_init($this->url($remotePath));
        curl_setopt_array($curl, [
            CURLOPT_USERPWD => $this->config->user . ':' . $this->config->password,
            CURLOPT_FAILONERROR => true,
            CURLOPT_CONNECTTIMEOUT => 20,
        ]);
        if ($this->config->scheme === 'ftps') {
            curl_setopt($curl, CURLOPT_USE_SSL, CURLUSESSL_ALL);
        }

        return $curl;
    }
}
