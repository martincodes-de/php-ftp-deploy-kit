<?php
declare(strict_types=1);

namespace Pfdk\Tests\Remote;

use Pfdk\Config\DeployConfig;
use Pfdk\Remote\CurlRemoteFiles;
use Pfdk\Tests\Support\FakeFtpServer;
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

    public function testFtpsUsesExplicitTlsLikeDgInsteadOfCurlsImplicitFtps(): void
    {
        $url = $this->urlFor('ftps://u:p@host/app');

        self::assertSame('ftp://host/app/db.sqlite', $url);
    }

    public function testOtherSchemesKeepTheirUrl(): void
    {
        self::assertSame('ftp://host/app/db.sqlite', $this->urlFor('ftp://u:p@host/app'));
        self::assertSame('sftp://host/app/db.sqlite', $this->urlFor('sftp://u:p@host/app'));
    }

    public function testConnectionErrorIsNotReportedAsMissingFile(): void
    {
        // Nothing listens on this port.
        $config = DeployConfig::fromArray('deploy.ini', ['remote' => 'ftp://user:pw@127.0.0.1:1/app', 'pfdk_retries' => '1']);

        $this->expectExceptionMessage('Download failed');
        (new CurlRemoteFiles($config))->download('db.sqlite', "{$this->dir}/db.sqlite");
    }

    public function testFailedDownloadLeavesNoPartialFile(): void
    {
        $config = DeployConfig::fromArray('deploy.ini', ['remote' => 'ftp://user:pw@127.0.0.1:1/app', 'pfdk_retries' => '1']);

        try {
            (new CurlRemoteFiles($config))->download('db.sqlite', "{$this->dir}/db.sqlite");
        } catch (\RuntimeException) {
        }

        self::assertFileDoesNotExist("{$this->dir}/db.sqlite");
    }

    public function testDownloadRetriesAfterATimeoutAndStartsTheFileOverEachTime(): void
    {
        $calls = [];
        $remote = $this->scripted([
            [false, 28, 'Connection time-out', 'partial'],
            [false, 7, 'Failed to connect', 'part'],
            [true, 0, '', 'live'],
        ], $calls, $pauses, $messages);

        self::assertTrue($remote->download('db.sqlite', "{$this->dir}/db.sqlite"));

        self::assertSame('live', file_get_contents("{$this->dir}/db.sqlite"));
        self::assertCount(3, $calls);
        self::assertSame([3, 3], $pauses);
        self::assertSame(['Connection failed or timed out, retrying (2/3) ...', 'Connection failed or timed out, retrying (3/3) ...'], $messages);
    }

    public function testGivesUpAfterTheLastAttemptWithAClearMessage(): void
    {
        $calls = [];
        $remote = $this->scripted([[false, 28, 'Connection time-out'], [false, 28, 'Connection time-out'], [false, 28, 'Connection time-out'], [true, 0, '']], $calls, $pauses);

        try {
            $remote->download('db.sqlite', "{$this->dir}/db.sqlite");
            self::fail('Expected the download to fail');
        } catch (\RuntimeException $e) {
            self::assertSame('Download failed after 3 attempts: Connection time-out', $e->getMessage());
        }
        self::assertCount(3, $calls);
        self::assertCount(2, $pauses);
        self::assertFileDoesNotExist("{$this->dir}/db.sqlite");
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('neverRetried')]
    public function testLoginAndNotFoundErrorsAreNeverRetried(int $errno, bool $throws): void
    {
        $calls = [];
        $remote = $this->scripted([[false, $errno, 'nope'], [true, 0, '']], $calls, $pauses);

        if ($throws) {
            $this->expectException(\RuntimeException::class);
        }
        try {
            $result = $remote->download('db.sqlite', "{$this->dir}/db.sqlite");
            self::assertFalse($result);
        } finally {
            self::assertCount(1, $calls);
            self::assertSame([], $pauses);
        }
    }

    /** @return iterable<string,array{int,bool}> */
    public static function neverRetried(): iterable
    {
        yield 'login denied' => [67, true];
        yield 'access denied' => [9, true];
        yield 'cannot retrieve' => [19, false];
        yield 'not found' => [78, false];
    }

    public function testDownloadRetriesTransferErrorsInTheMiddleOfTheTransfer(): void
    {
        foreach ([55, 56] as $errno) {
            $calls = [];
            $remote = $this->scripted([[false, $errno, 'broken', 'part'], [true, 0, '', 'live']], $calls, $pauses);

            self::assertTrue($remote->download('db.sqlite', "{$this->dir}/db.sqlite"));
            self::assertCount(2, $calls);
        }
    }

    public function testUploadGoesToATemporaryNameThenIsSizeCheckedThenRenamed(): void
    {
        $ftp = $this->ftp(['app/var/db.sqlite' => 'old!!!']);

        $this->upload($ftp, 'var/db.sqlite');

        self::assertSame(['PUT app/var/db.sqlite.pfdk-upload', 'SIZE app/var/db.sqlite.pfdk-upload', 'RENAME app/var/db.sqlite.pfdk-upload -> app/var/db.sqlite'], $ftp->log);
        self::assertSame(['app/var/db.sqlite' => 'local'], $ftp->files);
    }

    public function testUploadRetriesTheTransferOfTheTemporaryFile(): void
    {
        $ftp = $this->ftp();
        $ftp->fail('PUT', 56);

        $this->upload($ftp);

        self::assertCount(2, $ftp->commands('PUT'));
        self::assertSame([3], $this->pauses);
        self::assertSame(['app/db.sqlite' => 'local'], $ftp->files);
    }

    public function testUploadGivesUpWhenAllTransferAttemptsFailAndCleansUpOnce(): void
    {
        $ftp = $this->ftp(['app/db.sqlite' => 'old']);
        $ftp->fail('PUT', 28, times: 3);
        $ftp->fail('DELE', 28, times: 5);

        try {
            $this->upload($ftp);
            self::fail('Expected the upload to fail');
        } catch (\RuntimeException $e) {
            self::assertSame('Upload failed after 3 attempts: simulated error 28', $e->getMessage());
        }
        self::assertCount(3, $ftp->commands('PUT'));
        self::assertCount(1, $ftp->commands('DELE'), 'a dead server must not cost more attempts for the cleanup');
        self::assertSame('old', $ftp->files['app/db.sqlite']);
    }

    public function testLostAnswerOfACarriedOutRenameIsAnUploadThatWorked(): void
    {
        $ftp = $this->ftp(['app/db.sqlite' => 'old!!!']);
        $ftp->fail('RENAME', 28, afterEffect: true);

        $this->upload($ftp);

        self::assertCount(1, $ftp->commands('RENAME'), 'the rename is never repeated');
        self::assertSame([], $ftp->commands('DELE'));
        self::assertSame(['app/db.sqlite' => 'local'], $ftp->files);
    }

    /** @return iterable<string,array{int}> */
    public static function connectionErrors(): iterable
    {
        foreach ([7, 10, 28, 55, 56] as $errno) {
            yield "curl $errno" => [$errno];
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('connectionErrors')]
    public function testConnectionErrorAtTheRenameNeverDeletesTheTarget(int $errno): void
    {
        $ftp = $this->ftp(['app/db.sqlite' => 'old!!!']);
        $ftp->fail('RENAME', $errno);

        try {
            $this->upload($ftp);
            self::fail('Expected the upload to fail');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('the live file was not touched and the temporary file was removed', $e->getMessage());
        }
        self::assertSame(['DELE app/db.sqlite.pfdk-upload'], $ftp->commands('DELE'));
        self::assertSame(['app/db.sqlite' => 'old!!!'], $ftp->files);
    }

    public function testDeadConnectionAfterTheRenameMeansUnclearStateAndNothingIsDeleted(): void
    {
        $ftp = $this->ftp(['app/db.sqlite' => 'old!!!']);
        $ftp->fail('RENAME', 28);
        $ftp->fail('SIZE', 28, times: 2, skip: 1);

        try {
            $this->upload($ftp);
            self::fail('Expected the upload to fail');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('Upload state unclear', $e->getMessage());
            self::assertStringContainsString('app/db.sqlite.pfdk-upload', $e->getMessage());
            self::assertStringContainsString('app/db.sqlite', $e->getMessage());
        }
        self::assertSame([], $ftp->commands('DELE'));
        self::assertArrayHasKey('app/db.sqlite.pfdk-upload', $ftp->files);
        self::assertSame('old!!!', $ftp->files['app/db.sqlite']);
    }

    public function testServerThatRefusesRenameOverAnExistingFileGetsTheTargetDeletedFirst(): void
    {
        $ftp = $this->ftp(['app/db.sqlite' => 'old!!!']);
        $ftp->refuseRenameOverExisting = true;

        $this->upload($ftp);

        self::assertSame(
            ['PUT app/db.sqlite.pfdk-upload', 'SIZE app/db.sqlite.pfdk-upload', 'RENAME app/db.sqlite.pfdk-upload -> app/db.sqlite', 'SIZE app/db.sqlite.pfdk-upload', 'DELE app/db.sqlite', 'RENAME app/db.sqlite.pfdk-upload -> app/db.sqlite'],
            $ftp->log,
        );
        self::assertSame(['app/db.sqlite' => 'local'], $ftp->files);
    }

    public function testRefusedRenameWhoseTemporaryFileCannotBeConfirmedDeletesNothingOfTheTarget(): void
    {
        $ftp = $this->ftp(['app/db.sqlite' => 'old!!!']);
        $ftp->fail('RENAME', 21);
        $ftp->fail('SIZE', 28, path: 'pfdk-upload', skip: 1);

        try {
            $this->upload($ftp);
            self::fail('Expected the upload to fail');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('The live file was not touched.', $e->getMessage());
        }
        self::assertSame([], $ftp->commands('DELE app/db.sqlite'));
        self::assertSame('old!!!', $ftp->files['app/db.sqlite']);
    }

    public function testFailureAfterDeletingTheTargetKeepsAndNamesTheCompleteNewFile(): void
    {
        $ftp = $this->ftp(['app/db.sqlite' => 'old!!!']);
        $ftp->refuseRenameOverExisting = true;
        $ftp->fail('RENAME', 21, times: 2);

        try {
            $this->upload($ftp);
            self::fail('Expected the upload to fail');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('The old live file was removed; the complete new file is on the server as app/db.sqlite.pfdk-upload', $e->getMessage());
        }
        self::assertSame(['app/db.sqlite.pfdk-upload' => 'local'], $ftp->files);
    }

    public function testDeleteOfTheTargetThatTheServerRefusesLeavesItAndCleansUp(): void
    {
        $ftp = $this->ftp(['app/db.sqlite' => 'old!!!']);
        $ftp->fail('RENAME', 21);
        $ftp->fail('DELE', 21, path: 'app/db.sqlite');

        try {
            $this->upload($ftp);
            self::fail('Expected the upload to fail');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('The live file was not touched.', $e->getMessage());
        }
        self::assertSame(['app/db.sqlite' => 'old!!!'], $ftp->files);
    }

    public function testConnectionErrorWhileDeletingTheTargetIsReportedAsUnclear(): void
    {
        $ftp = $this->ftp(['app/db.sqlite' => 'old!!!']);
        $ftp->fail('RENAME', 21);
        $ftp->fail('DELE', 28, path: 'app/db.sqlite');

        $this->expectExceptionMessage('Upload state unclear after a connection error while deleting app/db.sqlite');
        $this->upload($ftp);
    }

    public function testSizeMismatchRemovesTheTemporaryFileAndNeverRenames(): void
    {
        $ftp = $this->ftp(['app/db.sqlite' => 'old!!!']);
        $ftp->keepOnlyBytes = 3;

        try {
            $this->upload($ftp);
            self::fail('Expected the upload to fail');
        } catch (\RuntimeException $e) {
            self::assertSame('Upload incomplete: 3 of 5 bytes arrived; the live file was not touched.', $e->getMessage());
        }
        self::assertSame([], $ftp->commands('RENAME'));
        self::assertSame(['app/db.sqlite' => 'old!!!'], $ftp->files);
    }

    public function testServerWithoutSizeSupportIsNotTrustedAndNothingIsRenamed(): void
    {
        $ftp = $this->ftp(['app/db.sqlite' => 'old!!!']);
        $ftp->reportNoSize = true;

        try {
            $this->upload($ftp);
            self::fail('Expected the upload to fail');
        } catch (\RuntimeException $e) {
            self::assertSame('Server reports no file size, so the upload cannot be verified and is not used; the live file was not touched.', $e->getMessage());
        }
        self::assertSame(['app/db.sqlite' => 'old!!!'], $ftp->files);
    }

    public function testFailedSizeCheckCleansUp(): void
    {
        $ftp = $this->ftp(['app/db.sqlite' => 'old!!!']);
        $ftp->fail('SIZE', 28);

        $this->expectExceptionMessage('Upload failed (could not check the size)');
        try {
            $this->upload($ftp);
        } finally {
            self::assertSame(['app/db.sqlite' => 'old!!!'], $ftp->files);
        }
    }

    public function testLineBreaksInARemotePathAreRejected(): void
    {
        $ftp = $this->ftp();

        $this->expectExceptionMessage('line break');
        $this->upload($ftp, "db.sqlite\r\nDELE app/other");
    }

    public function testFileTargetsAreReplacedWithoutLeavingTheTemporaryFile(): void
    {
        file_put_contents("{$this->serverDir}/db.sqlite", 'old');
        file_put_contents("{$this->dir}/local.sqlite", 'new');

        $this->remote()->upload("{$this->dir}/local.sqlite", 'db.sqlite');

        self::assertSame('new', file_get_contents("{$this->serverDir}/db.sqlite"));
        self::assertSame(['db.sqlite'], array_values(array_diff(scandir($this->serverDir), ['.', '..'])));
    }

    public function testFileTargetWithSpecialCharactersInItsNames(): void
    {
        $special = "{$this->dir}/ser ver %1";
        mkdir($special);
        file_put_contents("{$this->dir}/local.sqlite", 'new');
        $remote = new CurlRemoteFiles(DeployConfig::fromArray('deploy.ini', ['remote' => 'file://localhost' . str_replace(['%', ' '], ['%25', '%20'], $special)]));

        $remote->upload("{$this->dir}/local.sqlite", 'db #1?.sqlite');

        self::assertSame('new', file_get_contents("$special/db #1?.sqlite"));
        self::assertSame(['db #1?.sqlite'], array_values(array_diff(scandir($special), ['.', '..'])));
    }

    public function testFileTargetLeavesNoPartialFileWhenTheCopyFails(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            self::markTestSkipped('permissions do not apply to root');
        }
        file_put_contents("{$this->dir}/local.sqlite", 'new');
        chmod($this->serverDir, 0555);

        try {
            $this->remote()->upload("{$this->dir}/local.sqlite", 'db.sqlite');
            self::fail('Expected the upload to fail');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('cannot write db.sqlite', $e->getMessage());
        } finally {
            chmod($this->serverDir, 0777);
        }
        self::assertSame([], array_values(array_diff(scandir($this->serverDir), ['.', '..'])));
    }

    public function testFileTargetKeepsTheOldFileWhenTheRenameFails(): void
    {
        mkdir("{$this->serverDir}/db.sqlite");
        file_put_contents("{$this->dir}/local.sqlite", 'new');

        try {
            $this->remote()->upload("{$this->dir}/local.sqlite", 'db.sqlite');
            self::fail('Expected the upload to fail');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('old file was not touched', $e->getMessage());
        }
        self::assertFileDoesNotExist("{$this->serverDir}/db.sqlite.pfdk-upload");
        self::assertDirectoryExists("{$this->serverDir}/db.sqlite");
    }

    public function testOneAttemptMeansNoRetry(): void
    {
        $calls = [];
        $remote = $this->scripted([[false, 28, 'timeout'], [true, 0, '']], $calls, $pauses, retries: 1);

        $this->expectExceptionMessage('Download failed: timeout');
        try {
            $remote->download('db.sqlite', "{$this->dir}/db.sqlite");
        } finally {
            self::assertCount(1, $calls);
        }
    }

    /**
     * @param list<array{0:bool,1:int,2:string,3?:string,4?:int}> $script ok, curl errno, message, optional bytes written to the download handle, optional remote size
     * @param list<array{url:string,options:array<int,mixed>}> $calls
     * @param list<int>|null                                $pauses
     * @param list<string>|null                             $messages
     */
    private function scripted(array $script, array &$calls, ?array &$pauses, ?array &$messages = null, int $retries = 3): CurlRemoteFiles
    {
        $pauses = [];
        $messages = [];
        $executor = static function (string $url, array $options) use (&$script, &$calls): array {
            $calls[] = ['url' => $url, 'options' => $options];
            [$ok, $errno, $error, $bytes, $size] = array_shift($script) + [3 => '', 4 => 0];
            if (isset($options[CURLOPT_FILE])) {
                fwrite($options[CURLOPT_FILE], $bytes);
            }

            return ['ok' => $ok, 'errno' => $errno, 'error' => $error, 'size' => $size];
        };

        return new CurlRemoteFiles(
            DeployConfig::fromArray('deploy.ini', ['remote' => 'ftp://u:p@host/app', 'pfdk_retries' => (string) $retries]),
            $executor,
            static function (int $seconds) use (&$pauses): void {
                $pauses[] = $seconds;
            },
            static function (string $message) use (&$messages): void {
                $messages[] = $message;
            },
        );
    }

    /** @var list<int> */
    private array $pauses = [];

    /** @param array<string,string> $files */
    private function ftp(array $files = []): FakeFtpServer
    {
        $ftp = new FakeFtpServer();
        $ftp->files = $files;

        return $ftp;
    }

    private function upload(FakeFtpServer $ftp, string $name = 'db.sqlite'): void
    {
        file_put_contents("{$this->dir}/local.sqlite", 'local');
        $this->pauses = [];
        $remote = new CurlRemoteFiles(
            DeployConfig::fromArray('deploy.ini', ['remote' => 'ftp://u:p@host/app']),
            $ftp,
            function (int $seconds): void {
                $this->pauses[] = $seconds;
            },
        );

        $remote->upload("{$this->dir}/local.sqlite", $name);
    }

    private function urlFor(string $remote): string
    {
        return (new CurlRemoteFiles(DeployConfig::fromArray('deploy.ini', ['remote' => $remote])))->url('db.sqlite');
    }

    public function testSizeCheckDoesNotPrintTheServerHeadersToTheConsole(): void
    {
        file_put_contents("{$this->serverDir}/db.sqlite", 'live');
        $executor = new \ReflectionMethod($this->remote(), 'curlExecutor');

        ob_start();
        $result = $executor->invoke($this->remote(), 'file://localhost' . $this->serverDir . '/db.sqlite', [CURLOPT_NOBODY => true]);
        $printed = ob_get_clean();

        self::assertSame('', $printed);
        self::assertSame(4, $result['size']);
    }

    private function remote(): CurlRemoteFiles
    {
        return new CurlRemoteFiles(DeployConfig::fromArray('deploy.ini', ['remote' => 'file://localhost' . $this->serverDir]));
    }
}
