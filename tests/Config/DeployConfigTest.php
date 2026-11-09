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
        $config = $this->config("PFDK_DB = var/database.db\npfdk_backups = saved\npfdk_tool = bin/deployment\n");

        self::assertSame('var/database.db', $config->db);
        self::assertSame('saved', $config->backups);
        self::assertSame('bin/deployment', $config->tool);
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

    public function testDatabaseSettingIsRequiredForDatabaseCommands(): void
    {
        $this->expectExceptionMessage('pfdk_db');
        $this->config('')->requireDb();
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
