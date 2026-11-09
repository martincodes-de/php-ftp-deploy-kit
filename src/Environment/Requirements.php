<?php
declare(strict_types=1);

namespace Pfdk\Environment;

final class Requirements
{
    /**
     * @param list<string> $curlProtocols
     * @param list<string> $extensions
     */
    public function __construct(
        private readonly array $curlProtocols,
        private readonly bool $curlSsl,
        private readonly array $extensions,
    ) {
    }

    public static function fromRuntime(): self
    {
        $curl = curl_version();

        return new self($curl['protocols'], ($curl['features'] & CURL_VERSION_SSL) !== 0, get_loaded_extensions());
    }

    /**
     * pfdk's own transfers use curl; the deployment itself runs through dg/ftp-deployment, which has its own needs.
     *
     * @return list<string>
     */
    public function problems(string $scheme, bool $forDeploy): array
    {
        $problems = [];

        switch ($scheme) {
            case 'ftp':
                $this->requireProtocol($problems, 'ftp', 'FTP');
                break;
            case 'ftps':
                $this->requireProtocol($problems, 'ftp', 'FTP');
                if (!$this->curlSsl) {
                    $problems[] = 'ftps:// needs a curl build with SSL support.';
                }
                if ($forDeploy && !$this->hasExtension('openssl')) {
                    $problems[] = 'ftps:// needs the PHP extension openssl for the deployment.';
                }
                break;
            case 'sftp':
                $this->requireProtocol($problems, 'sftp', 'SFTP', ' (curl needs libssh2 support)');
                if ($forDeploy && !$this->hasExtension('ssh2')) {
                    $problems[] = 'sftp:// needs the PHP extension ssh2 for the deployment. Install it or use ftps://.';
                }
                break;
            case 'file':
                $this->requireProtocol($problems, 'file', 'local folder');
                break;
            default:
                $problems[] = "Unsupported scheme '$scheme://'. Use ftp://, ftps://, sftp:// or file://.";
        }

        return $problems;
    }

    /** @param list<string> $problems */
    private function requireProtocol(array &$problems, string $protocol, string $label, string $hint = ''): void
    {
        if (!in_array($protocol, $this->curlProtocols, true)) {
            $problems[] = "PHP's curl cannot speak $label$hint.";
        }
    }

    private function hasExtension(string $name): bool
    {
        return in_array($name, $this->extensions, true);
    }
}
