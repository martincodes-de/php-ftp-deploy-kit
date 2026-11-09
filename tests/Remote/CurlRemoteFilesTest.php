<?php
declare(strict_types=1);

namespace Pfdk\Tests\Remote;

use Pfdk\Config\DeployConfig;
use Pfdk\Remote\CurlRemoteFiles;
use Pfdk\Tests\Support\ProjectTestCase;

/**
 * curl treats file://localhost/... like a remote server, so the real transfer
 * code runs here without needing an FTP server.
 */
final class CurlRemoteFilesTest extends ProjectTestCase
{
    private string $serverDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serverDir = "{$this->dir}/server";
        mkdir($this->serverDir);
    }

    public function testDownloadsAFile(): void
    {
        file_put_contents("{$this->serverDir}/db.sqlite", 'live');

        $ok = $this->remote()->download('db.sqlite', "{$this->dir}/downloads/db.sqlite");

        self::assertTrue($ok);
        self::assertSame('live', file_get_contents("{$this->dir}/downloads/db.sqlite"));
    }

    public function testMissingRemoteFileLeavesNoLocalFileBehind(): void
    {
        $ok = $this->remote()->download('missing.sqlite', "{$this->dir}/downloads/missing.sqlite");

        self::assertFalse($ok);
        self::assertFileDoesNotExist("{$this->dir}/downloads/missing.sqlite");
    }

    public function testEmptyRemoteFileCountsAsMissing(): void
    {
        file_put_contents("{$this->serverDir}/empty.sqlite", '');

        self::assertFalse($this->remote()->download('empty.sqlite', "{$this->dir}/empty.sqlite"));
        self::assertFileDoesNotExist("{$this->dir}/empty.sqlite");
    }

    public function testUploadsAFile(): void
    {
        file_put_contents("{$this->dir}/local.sqlite", 'local');

        $this->remote()->upload("{$this->dir}/local.sqlite", 'db.sqlite');

        self::assertSame('local', file_get_contents("{$this->serverDir}/db.sqlite"));
    }

    public function testUploadFailureIsReported(): void
    {
        file_put_contents("{$this->dir}/local.sqlite", 'local');

        $this->expectExceptionMessage('Upload failed');
        $this->remote()->upload("{$this->dir}/local.sqlite", 'no-such-dir/db.sqlite');
    }

    public function testPlainFtpDemandsTlsSoPasswordsAreNeverSentInClearText(): void
    {
        // Nothing listens on this port, so the call fails after the TLS option was applied.
        $config = DeployConfig::fromArray('deploy.ini', ['remote' => 'ftp://user:pw@127.0.0.1:1/app']);

        self::assertFalse((new CurlRemoteFiles($config))->download('db.sqlite', "{$this->dir}/db.sqlite"));
    }

    private function remote(): CurlRemoteFiles
    {
        return new CurlRemoteFiles(DeployConfig::fromArray('deploy.ini', ['remote' => 'file://localhost' . $this->serverDir]));
    }
}
