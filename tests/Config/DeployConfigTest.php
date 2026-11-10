<?php
declare(strict_types=1);

namespace Pfdk\Tests\Config;

use Pfdk\Config\DeployConfig;
use Pfdk\Tests\Support\ProjectTestCase;

final class DeployConfigTest extends ProjectTestCase
{
    public function testParsesRemoteUrlWithEncodedCredentials(): void
    {
        $config = DeployConfig::fromArray('deploy.ini', ['remote' => '"ftp://me:p%40ss@example.org:2121/web/app/"']);

        self::assertSame('ftp', $config->scheme);
        self::assertSame('example.org:2121', $config->host);
        self::assertSame('me', $config->user);
        self::assertSame('p@ss', $config->password);
        self::assertSame('web/app', $config->remotePath);
    }

    public function testFallsBackToSeparateUserAndPasswordKeys(): void
    {
        $config = DeployConfig::fromArray('deploy.ini', ['remote' => 'sftp://host/path', 'user' => 'u', 'password' => 'pw']);

        self::assertSame(['u', 'pw'], [$config->user, $config->password]);
    }

    public function testLoadsPfdkSettingsFromIniFile(): void
    {
        $config = $this->config("PFDK_DB = var/database.db\npfdk_backups = saved\n");

        self::assertSame('var/database.db', $config->db);
        self::assertSame('saved', $config->backups);
    }

    public function testBackupRotationAndSymfonySettings(): void
    {
        $defaults = $this->config();
        $custom = $this->config("pfdk_backups_keep = 3\npfdk_type = Symfony\npfdk_migrate_command = \"ddev exec migrate\"\n");

        self::assertSame([10, null, null], [$defaults->backupsKeep, $defaults->type, $defaults->migrateCommand]);
        self::assertSame([3, 'symfony', 'ddev exec migrate'], [$custom->backupsKeep, $custom->type, $custom->migrateCommand]);
    }

    public function testDdevIsOnByDefaultAndCanBeSwitchedOff(): void
    {
        self::assertTrue($this->config()->ddev);
        self::assertTrue($this->config("pfdk_ddev = auto\n")->ddev);
        self::assertFalse($this->config("pfdk_ddev = off\n")->ddev);
        self::assertFalse($this->config("pfdk_ddev = false\n")->ddev);
    }

    public function testNonNumericBackupLimitIsRejected(): void
    {
        $this->expectExceptionMessage('pfdk_backups_keep must be a number');
        $this->config("pfdk_backups_keep = ten\n");
    }

    public function testNegativeBackupLimitMeansKeepEverything(): void
    {
        self::assertSame(0, $this->config("pfdk_backups_keep = -5\n")->backupsKeep);
    }

    public function testBackupsDefaultToFolderNextToTheIni(): void
    {
        $config = $this->config();

        self::assertSame("{$this->dir}/backups/a.db", $config->backupFile('a.db'));
    }

    public function testAbsoluteBackupFolderIsKept(): void
    {
        $config = $this->config("pfdk_backups = /var/backups/\n");

        self::assertSame('/var/backups/a.db', $config->backupFile('a.db'));
    }

    public function testBuildsRemoteUrls(): void
    {
        self::assertSame('ftp://example.org/web/app/var/database.db', $this->config()->remoteUrl('/var/database.db'));
        self::assertSame('ftp://h/x.db', DeployConfig::fromArray('i', ['remote' => 'ftp://h'])->remoteUrl('x.db'));
    }

    public function testProjectDirFollowsTheLocalKey(): void
    {
        mkdir("{$this->dir}/ftp-deployment");
        $config = DeployConfig::fromArray("{$this->dir}/ftp-deployment/deploy.ini", [
            'remote' => 'ftp://h/p',
            'local' => '..',
            'pfdk_db' => 'var/app.db',
            'pfdk_backups' => 'ftp-deployment/backups',
        ]);

        self::assertSame(realpath($this->dir), $config->projectDir());
        self::assertSame(realpath($this->dir) . '/var/app.db', $config->localDbFile());
        self::assertSame(realpath($this->dir) . '/ftp-deployment/backups/a.db', $config->backupFile('a.db'));
    }

    public function testProjectDirDefaultsToTheIniFolder(): void
    {
        self::assertSame(realpath($this->dir), $this->config()->projectDir());
    }

    public function testAbsoluteLocalPathIsTheProjectDir(): void
    {
        $config = DeployConfig::fromArray("{$this->dir}/deploy.ini", ['remote' => 'ftp://h/p', 'local' => '/somewhere/else']);

        self::assertSame('/somewhere/else', $config->projectDir());
    }

    public function testResolvesLocalDatabaseInsideProject(): void
    {
        self::assertSame("{$this->dir}/var/database.db", $this->config()->localDbFile());
    }

    public function testLocalDatabaseDefaultsToTheRemotePath(): void
    {
        $config = $this->config("pfdk_db = var/data_prod.db\n");

        self::assertNull($config->dbLocal);
        self::assertSame("{$this->dir}/var/data_prod.db", $config->localDbFile());
    }

    public function testLocalDatabaseCanDifferFromTheRemotePath(): void
    {
        $config = $this->config("pfdk_db = var/data_prod.db\npfdk_db_local = \"/var/data_dev.db\"\n");

        self::assertSame('var/data_prod.db', $config->requireDb());
        self::assertSame("{$this->dir}/var/data_dev.db", $config->localDbFile());
    }

    public function testEmptyOrSlashOnlyLocalDatabaseCountsAsNotSet(): void
    {
        foreach (['', '"/"', '" "'] as $value) {
            $config = $this->config("pfdk_db = var/data_prod.db\npfdk_db_local = $value\n");

            self::assertNull($config->dbLocal, "value: $value");
            self::assertSame("{$this->dir}/var/data_prod.db", $config->localDbFile());
        }
    }

    public function testDatabaseSettingIsRequiredForDatabaseCommands(): void
    {
        $this->expectExceptionMessage('pfdk_db');
        $this->config('')->requireDb();
    }

    public function testSectionOverridesTheTopLevelDefaults(): void
    {
        $file = $this->writeSectionedIni();

        $production = DeployConfig::load($file, 'production');
        $staging = DeployConfig::load($file, 'staging');

        self::assertSame(['example.org', 'var/app.db', 'backups-prod', 'production'], [$production->host, $production->db, $production->backups, $production->section]);
        self::assertSame(['staging.example.org', 'var/app.db', 'backups'], [$staging->host, $staging->db, $staging->backups]);
    }

    public function testSectionedIniNeedsAnExplicitSection(): void
    {
        $file = $this->writeSectionedIni();

        $this->expectExceptionMessage('pick one with --section: staging, production');
        DeployConfig::load($file);
    }

    public function testUnknownSectionListsTheAvailableOnes(): void
    {
        $file = $this->writeSectionedIni();

        $this->expectExceptionMessage("Unknown section 'live'. Available: staging, production");
        DeployConfig::load($file, 'live');
    }

    public function testSectionOnASingleTargetIniIsRejected(): void
    {
        $file = $this->writeIni("before[] = \"local: one\"\nbefore[] = \"local: two\"\n");

        $this->expectExceptionMessage('has no sections');
        DeployConfig::load($file, 'production');
    }

    public function testJobListsAreNotMistakenForSections(): void
    {
        $file = $this->writeIni("before[] = \"local: one\"\nbefore[] = \"local: two\"\n");

        self::assertSame('example.org', DeployConfig::load($file)->host);
    }

    private function writeSectionedIni(): string
    {
        $file = "{$this->dir}/sections.ini";
        file_put_contents($file, <<<INI
            local = .
            pfdk_db = var/app.db

            [staging]
            remote = "ftps://u:p@staging.example.org/app"

            [production]
            remote = "ftps://u:p@example.org/app"
            pfdk_backups = backups-prod
            INI);

        return $file;
    }

    public function testFileRemoteNeedsNoHost(): void
    {
        $config = DeployConfig::fromArray('deploy.ini', ['remote' => '"file:///srv/target"']);

        self::assertSame('file:///srv/target/var/app.db', $config->remoteUrl('var/app.db'));
    }

    public function testOtherSchemesStillNeedAHost(): void
    {
        $this->expectExceptionMessage('ftp://host/path');
        DeployConfig::fromArray('deploy.ini', ['remote' => 'ftp:///path']);
    }

    public function testRejectsMissingFile(): void
    {
        $this->expectExceptionMessage('Config not found');
        DeployConfig::load("{$this->dir}/nope.ini");
    }

    public function testRejectsUnparsableFile(): void
    {
        file_put_contents("{$this->dir}/broken.ini", "remote = \"unterminated\n[[");

        $this->expectExceptionMessage('Cannot parse');
        DeployConfig::load("{$this->dir}/broken.ini");
    }

    public function testRejectsRemoteWithoutHost(): void
    {
        $this->expectExceptionMessage('ftp://host/path');
        DeployConfig::fromArray('deploy.ini', ['remote' => 'nonsense']);
    }
}
