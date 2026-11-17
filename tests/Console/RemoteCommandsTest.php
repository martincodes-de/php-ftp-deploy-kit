<?php
declare(strict_types=1);

namespace Pfdk\Tests\Console;

use Pfdk\Console\ApplicationFactory;
use Pfdk\Tests\Support\InMemoryRemoteFiles;
use Pfdk\Tests\Support\ProjectTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class RemoteCommandsTest extends ProjectTestCase
{
    private InMemoryRemoteFiles $remote;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeIni();
        $this->remote = new InMemoryRemoteFiles();
    }

    public function testMkdirCreatesNestedFolders(): void
    {
        $tester = $this->execute('remote:mkdir', ['path' => '/var/database/']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertArrayHasKey('var', $this->remote->folders);
        self::assertArrayHasKey('var/database', $this->remote->folders);
    }

    public function testMkdirAndRmRefuseDangerousPaths(): void
    {
        foreach (['', '/', '.', '../etc', 'a/../b'] as $path) {
            foreach (['remote:mkdir' => [], 'remote:rm' => ['--recursive' => true, '--yes' => true]] as $name => $options) {
                try {
                    $this->execute($name, ['path' => $path] + $options);
                    self::fail("$name accepted '$path'");
                } catch (\RuntimeException $e) {
                    self::assertNotSame('', $e->getMessage());
                }
            }
        }
    }

    public function testLsPrintsTheNamesOfAFolder(): void
    {
        $this->remote->files['var/database/live.db'] = 'x';
        $this->remote->files['var/note.txt'] = 'x';

        $tester = $this->execute('remote:ls', ['path' => 'var']);

        self::assertStringContainsString('database', $tester->getDisplay());
        self::assertStringContainsString('note.txt', $tester->getDisplay());
        self::assertStringNotContainsString('live.db', $tester->getDisplay());
    }

    public function testLsWithoutPathListsTheRemoteRootAndSaysWhenEmpty(): void
    {
        self::assertStringContainsString('(empty)', $this->execute('remote:ls')->getDisplay());

        $this->remote->files['index.php'] = 'x';
        self::assertStringContainsString('index.php', $this->execute('remote:ls')->getDisplay());
    }

    public function testLsOfAMissingFolderFails(): void
    {
        $this->expectExceptionMessage('Listing failed');
        $this->execute('remote:ls', ['path' => 'nope']);
    }

    public function testRmRecursiveDeletesFilesFirstAndFoldersLast(): void
    {
        $this->remote->files['var/database/live.db'] = 'x';
        $this->remote->files['var/cache/a'] = 'x';
        $this->remote->files['keep.txt'] = 'x';

        $tester = $this->execute('remote:rm', ['path' => 'var', '--recursive' => true, '--yes' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame(['keep.txt' => 'x'], $this->remote->files);
        self::assertSame([], $this->remote->folders);
    }

    public function testRmWithoutRecursiveDeletesOneFileOrAnEmptyFolderButNotAFullOne(): void
    {
        $this->remote->files['a.txt'] = 'x';
        $this->remote->files['full/b.txt'] = 'x';
        $this->remote->folders['empty'] = true;

        self::assertSame(0, $this->execute('remote:rm', ['path' => 'a.txt', '--yes' => true])->getStatusCode());
        self::assertSame(0, $this->execute('remote:rm', ['path' => 'empty', '--yes' => true])->getStatusCode());

        $tester = $this->execute('remote:rm', ['path' => 'full', '--yes' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('use -r', $tester->getDisplay());
        self::assertSame(['full/b.txt' => 'x'], $this->remote->files);
    }

    public function testRmOfAMissingPathFails(): void
    {
        $tester = $this->execute('remote:rm', ['path' => 'ghost', '--recursive' => true, '--yes' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('does not exist', $tester->getDisplay());
    }

    public function testRmAsksAndCanBeAborted(): void
    {
        $this->remote->files['a.txt'] = 'x';

        $tester = $this->execute('remote:rm', ['path' => 'a.txt'], ['n']);

        self::assertStringContainsString('Aborted.', $tester->getDisplay());
        self::assertArrayHasKey('a.txt', $this->remote->files);
    }

    public function testUploadCopiesALocalFileAndAsksFirst(): void
    {
        file_put_contents("{$this->dir}/notes.txt", 'local');

        $aborted = $this->execute('remote:upload', ['local' => 'notes.txt', 'remote' => 'docs/notes.txt'], ['n']);
        self::assertStringContainsString('Aborted.', $aborted->getDisplay());
        self::assertSame([], $this->remote->files);

        $tester = $this->execute('remote:upload', ['local' => 'notes.txt', 'remote' => 'docs/notes.txt', '--yes' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame('local', $this->remote->files['docs/notes.txt']);
    }

    public function testUploadWithoutLocalFileFailsAndUploadsNothing(): void
    {
        $tester = $this->execute('remote:upload', ['local' => 'missing.txt', 'remote' => 'x.txt', '--yes' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertSame([], $this->remote->files);
    }

    public function testDownloadWritesTheFileAndCreatesLocalFolders(): void
    {
        $this->remote->files['docs/notes.txt'] = 'live';

        $tester = $this->execute('remote:download', ['remote' => 'docs/notes.txt', 'local' => 'got/notes.txt']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame('live', file_get_contents("{$this->dir}/got/notes.txt"));
        self::assertSame([], glob("{$this->dir}/got/*.pfdk-download"));
    }

    public function testDownloadOfAMissingFileFailsAndLeavesTheLocalFileAlone(): void
    {
        file_put_contents("{$this->dir}/notes.txt", 'precious');

        $tester = $this->execute('remote:download', ['remote' => 'ghost.txt', 'local' => 'notes.txt', '--yes' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertSame('precious', file_get_contents("{$this->dir}/notes.txt"));
        self::assertFileDoesNotExist("{$this->dir}/notes.txt.pfdk-download");
    }

    public function testDownloadAsksBeforeReplacingALocalFile(): void
    {
        file_put_contents("{$this->dir}/notes.txt", 'precious');
        $this->remote->files['n.txt'] = 'live';

        $tester = $this->execute('remote:download', ['remote' => 'n.txt', 'local' => 'notes.txt'], ['n']);

        self::assertStringContainsString('Aborted.', $tester->getDisplay());
        self::assertSame('precious', file_get_contents("{$this->dir}/notes.txt"));
    }

    public function testDbInitCreatesTheFolderAndUploadsTheLocalDatabase(): void
    {
        $this->writeIni("pfdk_db = var/database/app.db\n");
        mkdir("{$this->dir}/var/database", 0777, true);
        file_put_contents("{$this->dir}/var/database/app.db", 'local');

        $tester = $this->execute('db:init');

        self::assertSame(0, $tester->getStatusCode());
        self::assertArrayHasKey('var/database', $this->remote->folders);
        self::assertSame('local', $this->remote->files['var/database/app.db']);
    }

    public function testDbInitNeverOverwritesALiveDatabase(): void
    {
        $this->writeLocalDb('local');
        $this->remote->files['var/database.db'] = 'live';

        $tester = $this->execute('db:init');

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('db:push', $tester->getDisplay());
        self::assertSame('live', $this->remote->files['var/database.db']);
    }

    public function testDbInitNeedsTheLocalDatabaseAndTouchesNothingWithout(): void
    {
        $tester = $this->execute('db:init');

        self::assertSame(1, $tester->getStatusCode());
        self::assertSame([], $this->remote->folders);
    }

    public function testDbInitWithoutPfdkDbExplainsWhatToSet(): void
    {
        $this->writeIni('');

        $this->expectExceptionMessage('pfdk_db');
        $this->execute('db:init');
    }

    public function testUnreachableServerStopsEveryRemoteCommand(): void
    {
        $this->remote->unreachable = true;
        $this->writeLocalDb('local');

        foreach (['remote:ls' => [], 'remote:mkdir' => ['path' => 'a'], 'db:init' => []] as $name => $arguments) {
            try {
                $this->execute($name, $arguments);
                self::fail("$name did not fail");
            } catch (\RuntimeException $e) {
                self::assertStringContainsString('refused', $e->getMessage());
            }
        }
    }

    /** @param array<string,mixed> $arguments */
    private function execute(string $name, array $arguments = [], array $inputs = []): CommandTester
    {
        $tester = new CommandTester(ApplicationFactory::create(fn () => $this->remote)->find($name));
        $tester->setInputs($inputs);
        $tester->execute($arguments, ['interactive' => $inputs !== []]);

        return $tester;
    }
}
