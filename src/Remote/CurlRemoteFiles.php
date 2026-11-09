<?php
declare(strict_types=1);

namespace Pfdk\Remote;

use Pfdk\Config\DeployConfig;

final class CurlRemoteFiles implements RemoteFiles
{
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
        curl_close($curl);
        fclose($handle);

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

    private function curl(string $remotePath): \CurlHandle
    {
        $curl = curl_init($this->config->remoteUrl($remotePath));
        curl_setopt_array($curl, [
            CURLOPT_USERPWD => $this->config->user . ':' . $this->config->password,
            CURLOPT_FAILONERROR => true,
            CURLOPT_CONNECTTIMEOUT => 20,
        ]);
        // Plain ftp:// would send the password in clear text, so TLS is mandatory.
        if ($this->config->scheme === 'ftp') {
            curl_setopt($curl, CURLOPT_USE_SSL, CURLUSESSL_ALL);
        }

        return $curl;
    }
}
