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
        public readonly string $local = '.',
        public readonly int $backupsKeep = 10,
        public readonly ?string $type = null,
        public readonly ?string $migrateCommand = null,
        public readonly bool $ddev = true,
        public readonly ?string $section = null,
        public readonly ?string $dbLocal = null,
        /** @var list<string> */
        public readonly array $finally = [],
        public readonly int $retries = 3,
    ) {
    }

    public static function load(string $file, ?string $section = null): self
    {
        if (!is_file($file)) {
            throw new ConfigNotFound("Config not found: $file (create it with `pfdk init <preset>`).");
        }
        // The warning would duplicate the exception below.
        $raw = @parse_ini_file($file, true);
        if ($raw === false) {
            throw new \RuntimeException("Cannot parse $file");
        }

        return self::fromArray($file, self::selectSection($raw, $section), $section);
    }

    /** @param array<string,mixed> $values keys already lower-cased */
    public static function fromArray(string $file, array $values, ?string $section = null): self
    {
        $remote = self::unquote((string) ($values['remote'] ?? ''));
        $parts = parse_url($remote);
        if ($remote === '' || $parts === false || !isset($parts['scheme']) || (!isset($parts['host']) && $parts['scheme'] !== 'file')) {
            throw new \RuntimeException('"remote" in the ini must look like ftp://host/path');
        }

        return new self(
            $file,
            $parts['scheme'],
            ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : ''),
            isset($parts['user']) ? rawurldecode($parts['user']) : self::unquote((string) ($values['user'] ?? '')),
            isset($parts['pass']) ? rawurldecode($parts['pass']) : self::unquote((string) ($values['password'] ?? '')),
            trim($parts['path'] ?? '', '/'),
            isset($values['pfdk_db']) ? trim(self::unquote((string) $values['pfdk_db']), '/') : null,
            self::unquote((string) ($values['pfdk_backups'] ?? 'backups')),
            self::unquote((string) ($values['local'] ?? '.')),
            self::backupsKeep($values['pfdk_backups_keep'] ?? 10),
            isset($values['pfdk_type']) ? strtolower(self::unquote((string) $values['pfdk_type'])) : null,
            isset($values['pfdk_migrate_command']) ? trim((string) $values['pfdk_migrate_command']) : null,
            self::ddevEnabled($values['pfdk_ddev'] ?? 'auto'),
            $section,
            self::optionalPath($values['pfdk_db_local'] ?? null),
            self::steps($values['pfdk_finally'] ?? []),
            self::retries($values['pfdk_retries'] ?? 3),
        );
    }

    /**
     * @param array<string,mixed> $raw
     *
     * @return array<string,mixed> the settings of the chosen target, keys lower-cased
     */
    private static function selectSection(array $raw, ?string $section): array
    {
        // Lists such as before[] are arrays too; sections are the keyed ones.
        $sections = array_filter($raw, static fn (mixed $value): bool => is_array($value) && !array_is_list($value));
        $top = array_change_key_case(array_diff_key($raw, $sections), CASE_LOWER);

        // Same rule as dg: a top-level remote means one deployment, no sections.
        if (is_string($top['remote'] ?? null)) {
            if ($section !== null) {
                throw new \RuntimeException("--section $section was given, but this ini has no sections.");
            }

            return $top;
        }
        if ($sections === []) {
            return $top;
        }

        if ($section === null) {
            throw new SectionRequired(array_keys($sections));
        }
        if (!isset($sections[$section])) {
            throw new \RuntimeException("Unknown section '$section'. Available: " . implode(', ', array_keys($sections)));
        }

        return array_change_key_case($sections[$section], CASE_LOWER) + $top;
    }

    /** The folder dg deploys (`local`), because the ini often lives in a subfolder such as ftp-deployment/. */
    public function projectDir(): string
    {
        $absolute = preg_match('~^(/|[A-Za-z]:)~', $this->local) === 1;
        $path = $absolute ? $this->local : dirname($this->file) . '/' . $this->local;

        return realpath($path) ?: $path;
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
        return $this->projectDir() . '/' . ($this->dbLocal ?? $this->requireDb());
    }

    public function backupFile(string $name): string
    {
        $absolute = preg_match('~^(/|[A-Za-z]:)~', $this->backups) === 1;

        return rtrim($absolute ? $this->backups : $this->projectDir() . '/' . $this->backups, '/') . '/' . $name;
    }

    private static function ddevEnabled(mixed $value): bool
    {
        // PHP's ini parser turns `off` into an empty string.
        return !in_array(strtolower(trim((string) $value)), ['', '0', 'off', 'no', 'false'], true);
    }

    private static function retries(mixed $value): int
    {
        $text = trim((string) $value);
        if (preg_match('/^\d+$/', $text) !== 1 || (int) $text < 1 || (int) $text > 10) {
            throw new \RuntimeException('pfdk_retries must be a whole number from 1 to 10 (attempts in total; 1 = no retry)');
        }

        return (int) $text;
    }

    private static function backupsKeep(mixed $value): int
    {
        // A typo must not silently turn rotation off.
        if (!is_numeric($value)) {
            throw new \RuntimeException('pfdk_backups_keep must be a number');
        }

        return max(0, (int) $value);
    }

    private static function optionalPath(mixed $value): ?string
    {
        $path = trim(self::unquote((string) $value), '/');

        return $path === '' ? null : $path;
    }

    /** @return list<string> */
    private static function steps(mixed $value): array
    {
        return array_values(array_filter(
            array_map(static fn (mixed $step): string => trim((string) $step), (array) $value),
            static fn (string $step): bool => $step !== '',
        ));
    }

    private static function unquote(string $value): string
    {
        return trim($value, " \t\"'");
    }
}
