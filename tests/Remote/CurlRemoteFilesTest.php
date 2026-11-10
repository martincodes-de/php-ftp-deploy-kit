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

    public function testUploadRetriesToo(): void
    {
        file_put_contents("{$this->dir}/local.sqlite", 'local');
        $calls = [];
        $remote = $this->scripted([[false, 28, 'timeout'], [true, 0, ''], [true, 0, '', '', 5], [true, 0, '']], $calls, $pauses);

        $remote->upload("{$this->dir}/local.sqlite", 'db.sqlite');

        self::assertCount(4, $calls);
        self::assertSame([3], $pauses);
    }

    public function testUploadGoesToATemporaryNameThenIsSizeCheckedThenRenamed(): void
    {
        file_put_contents("{$this->dir}/local.sqlite", 'local');
        $calls = [];
        $remote = $this->scripted([[true, 0, ''], [true, 0, '', '', 5], [true, 0, '']], $calls, $pauses);

        $remote->upload("{$this->dir}/local.sqlite", 'var/db.sqlite');

        self::assertSame(
            ['PUT ftp://host/app/var/db.sqlite.pfdk-upload', 'SIZE ftp://host/app/var/db.sqlite.pfdk-upload', 'ftp://host/app/ RNFR app/var/db.sqlite.pfdk-upload | RNTO app/var/db.sqlite'],
            array_map($this->describe(...), $calls),
        );
    }

    public function testRenameOverAnExistingFileFallsBackToDeletingTheTargetFirstLikeDg(): void
    {
        file_put_contents("{$this->dir}/local.sqlite", 'local');
        $calls = [];
        $remote = $this->scripted([[true, 0, ''], [true, 0, '', '', 5], [false, 21, 'RNTO refused'], [true, 0, ''], [true, 0, '']], $calls, $pauses);

        $remote->upload("{$this->dir}/local.sqlite", 'db.sqlite');

        self::assertSame(
            ['ftp://host/app/ RNFR app/db.sqlite.pfdk-upload | RNTO app/db.sqlite', 'ftp://host/app/ DELE app/db.sqlite', 'ftp://host/app/ RNFR app/db.sqlite.pfdk-upload | RNTO app/db.sqlite'],
            array_slice(array_map($this->describe(...), $calls), 2),
        );
    }

    public function testSizeMismatchRemovesTheTemporaryFileAndNeverRenames(): void
    {
        file_put_contents("{$this->dir}/local.sqlite", 'local');
        $calls = [];
        $remote = $this->scripted([[true, 0, ''], [true, 0, '', '', 3], [true, 0, '']], $calls, $pauses);

        try {
            $remote->upload("{$this->dir}/local.sqlite", 'db.sqlite');
            self::fail('Expected the upload to fail');
        } catch (\RuntimeException $e) {
            self::assertSame('Upload incomplete: 3 of 5 bytes arrived; the live file was not touched.', $e->getMessage());
        }
        self::assertSame('ftp://host/app/ DELE app/db.sqlite.pfdk-upload', $this->describe($calls[2]));
        self::assertCount(3, $calls);
    }

    public function testUnknownRemoteSizeCountsAsFailure(): void
    {
        file_put_contents("{$this->dir}/local.sqlite", 'local');
        $calls = [];
        $remote = $this->scripted([[true, 0, ''], [true, 0, '', '', -1], [true, 0, '']], $calls, $pauses);

        $this->expectExceptionMessage('Upload incomplete');
        $remote->upload("{$this->dir}/local.sqlite", 'db.sqlite');
    }

    public function testFailedTransferRemovesTheTemporaryFile(): void
    {
        file_put_contents("{$this->dir}/local.sqlite", 'local');
        $calls = [];
        $remote = $this->scripted([[false, 13, 'broken pipe'], [true, 0, '']], $calls, $pauses);

        try {
            $remote->upload("{$this->dir}/local.sqlite", 'db.sqlite');
            self::fail('Expected the upload to fail');
        } catch (\RuntimeException $e) {
            self::assertSame('Upload failed: broken pipe', $e->getMessage());
        }
        self::assertSame('ftp://host/app/ DELE app/db.sqlite.pfdk-upload', $this->describe($calls[1]));
    }

    public function testFailedRenameWithAnUntouchedTargetCleansUp(): void
    {
        file_put_contents("{$this->dir}/local.sqlite", 'local');
        $calls = [];
        $remote = $this->scripted([[true, 0, ''], [true, 0, '', '', 5], [false, 21, 'no'], [false, 21, 'no such file'], [false, 21, 'no'], [true, 0, '']], $calls, $pauses);

        try {
            $remote->upload("{$this->dir}/local.sqlite", 'db.sqlite');
            self::fail('Expected the upload to fail');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('The live file was not touched.', $e->getMessage());
        }
        self::assertSame('ftp://host/app/ DELE app/db.sqlite.pfdk-upload', $this->describe($calls[5]));
    }

    public function testFailedRenameAfterTheTargetWasDeletedKeepsTheCompleteNewFile(): void
    {
        file_put_contents("{$this->dir}/local.sqlite", 'local');
        $calls = [];
        $remote = $this->scripted([[true, 0, ''], [true, 0, '', '', 5], [false, 21, 'no'], [true, 0, ''], [false, 21, 'no']], $calls, $pauses);

        try {
            $remote->upload("{$this->dir}/local.sqlite", 'db.sqlite');
            self::fail('Expected the upload to fail');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('on the server as db.sqlite.pfdk-upload', $e->getMessage());
        }
        self::assertCount(5, $calls, 'the temporary file is not deleted');
    }

    public function testFileTargetsAreReplacedWithoutLeavingTheTemporaryFile(): void
    {
        file_put_contents("{$this->serverDir}/db.sqlite", 'old');
        file_put_contents("{$this->dir}/local.sqlite", 'new');

        $this->remote()->upload("{$this->dir}/local.sqlite", 'db.sqlite');

        self::assertSame('new', file_get_contents("{$this->serverDir}/db.sqlite"));
        self::assertSame(['db.sqlite'], array_values(array_diff(scandir($this->serverDir), ['.', '..'])));
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

    /** @param array{url:string,options:array<int,mixed>} $call */
    private function describe(array $call): string
    {
        $options = $call['options'];
        if (isset($options[CURLOPT_UPLOAD])) {
            return 'PUT ' . $call['url'];
        }
        if (isset($options[CURLOPT_QUOTE])) {
            return $call['url'] . ' ' . implode(' | ', $options[CURLOPT_QUOTE]);
        }

        return 'SIZE ' . $call['url'];
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

    private function urlFor(string $remote): string
    {
        return (new CurlRemoteFiles(DeployConfig::fromArray('deploy.ini', ['remote' => $remote])))->url('db.sqlite');
    }

    private function remote(): CurlRemoteFiles
    {
        return new CurlRemoteFiles(DeployConfig::fromArray('deploy.ini', ['remote' => 'file://localhost' . $this->serverDir]));
    }
}
