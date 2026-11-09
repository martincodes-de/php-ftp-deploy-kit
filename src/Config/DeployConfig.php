<?php
declare(strict_types=1);

namespace Pfdk\Config;

/** dg/ftp-deployment ignores unknown ini keys, so pfdk_* settings can share its file. */
final class DeployConfig
{
    public function __construct(
        public readonly string $file,
        public readonly string $scheme,
        public readonly string $host,
        public readonly string $user,
        public readonly string $password,
        public readonly string $remotePath,
        public readonly ?string $db,
        public readonly string $backups,
        public readonly ?string $tool,
    ) {
    }

    public static function load(string $file): self
    {
        if (!is_file($file)) {
            throw new \RuntimeException("Config not found: $file (create it with `pfdk init <preset>`).");
        }
        // The warning would duplicate the exception below.
        $raw = @parse_ini_file($file);
        if ($raw === false) {
            throw new \RuntimeException("Cannot parse $file");
        }

        return self::fromArray($file, array_change_key_case($raw, CASE_LOWER));
    }

    /** @param array<string,mixed> $values keys already lower-cased */
    public static function fromArray(string $file, array $values): self
    {
        $remote = self::unquote((string) ($values['remote'] ?? ''));
        $parts = parse_url($remote);
        if ($remote === '' || $parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new \RuntimeException('"remote" in the ini must look like ftp://host/path');
        }

        return new self(
            $file,
            $parts['scheme'],
            $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : ''),
            isset($parts['user']) ? rawurldecode($parts['user']) : self::unquote((string) ($values['user'] ?? '')),
            isset($parts['pass']) ? rawurldecode($parts['pass']) : self::unquote((string) ($values['password'] ?? '')),
            trim($parts['path'] ?? '', '/'),
            isset($values['pfdk_db']) ? trim(self::unquote((string) $values['pfdk_db']), '/') : null,
            self::unquote((string) ($values['pfdk_backups'] ?? 'backups')),
            isset($values['pfdk_tool']) ? self::unquote((string) $values['pfdk_tool']) : null,
        );
    }

    public function projectDir(): string
    {
        return dirname($this->file);
    }

    public function remoteUrl(string $relative): string
    {
        $path = ($this->remotePath !== '' ? $this->remotePath . '/' : '') . ltrim($relative, '/');

        return sprintf('%s://%s/%s', $this->scheme, $this->host, $path);
    }

    public function requireDb(): string
    {
        return $this->db ?? throw new \RuntimeException('Set pfdk_db = path/to/database in the ini to use database commands.');
    }

    public function localDbFile(): string
    {
        return $this->projectDir() . '/' . $this->requireDb();
    }

    public function backupFile(string $name): string
    {
        $absolute = preg_match('~^(/|[A-Za-z]:)~', $this->backups) === 1;

        return rtrim($absolute ? $this->backups : $this->projectDir() . '/' . $this->backups, '/') . '/' . $name;
    }

    private static function unquote(string $value): string
    {
        return trim($value, " \t\"'");
    }
}
