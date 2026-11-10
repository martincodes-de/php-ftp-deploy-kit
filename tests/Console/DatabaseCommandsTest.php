<?php
declare(strict_types=1);

namespace Pfdk\Tests\Console;

use Pfdk\Console\ApplicationFactory;
use Pfdk\Tests\Support\InMemoryRemoteFiles;
use Pfdk\Tests\Support\ProjectTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class DatabaseCommandsTest extends ProjectTestCase
{
    private const LIVE = 'var/database.db';

    private InMemoryRemoteFiles $remote;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeIni();
        $this->remote = new InMemoryRemoteFiles();
    }

    public function testBackupStoresTheLiveDatabase(): void
    {
        $this->remote->files[self::LIVE] = 'live';

        $tester = $this->execute('backup');

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame('live', $this->onlyFile('backups/database-*.db'));
    }

    public function testBackupWithoutLiveDatabaseIsNotAnError(): void
    {
        $tester = $this->execute('backup');

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('nothing backed up', $tester->getDisplay());
    }

    public function testPullReplacesLocalDatabaseAndKeepsBothVersions(): void
    {
        $local = $this->writeLocalDb('old local');
        $this->remote->files[self::LIVE] = 'live';

        $tester = $this->execute('db:pull');

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame('live', file_get_contents($local));
        self::assertSame('old local', $this->onlyFile('var/database.db.bak-local-*'));
        self::assertSame('live', $this->onlyFile('backups/database-pulled-*.db'));
    }

    public function testPullWorksWithoutExistingLocalDatabase(): void
    {
        $this->remote->files[self::LIVE] = 'live';

        $this->execute('db:pull');

        self::assertSame('live', file_get_contents("{$this->dir}/var/database.db"));
        self::assertSame([], glob("{$this->dir}/var/*.bak-*"));
    }

    public function testFailedPullLeavesLocalDatabaseUntouched(): void
    {
        $local = $this->writeLocalDb('precious');

        $tester = $this->execute('db:pull');

        self::assertSame(1, $tester->getStatusCode());
        self::assertSame('precious', file_get_contents($local));
        self::assertSame([], glob("{$this->dir}/var/*.bak-*"));
    }

    public function testPushBacksUpLiveDatabaseThenOverwritesIt(): void
    {
        $this->writeLocalDb('local');
        $this->remote->files[self::LIVE] = 'live';

        $tester = $this->execute('db:push', ['--yes' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame('live', $this->onlyFile('backups/database-pre-push-*.db'));
        self::assertSame('local', $this->remote->files[self::LIVE]);
    }

    public function testFirstPushHasNothingToBackUp(): void
    {
        $this->writeLocalDb('local');

        $tester = $this->execute('db:push', ['--yes' => true]);

        self::assertStringContainsString('first push', $tester->getDisplay());
        self::assertSame('local', $this->remote->files[self::LIVE]);
    }

    public function testPushCanBeAbortedAtThePrompt(): void
    {
        $this->writeLocalDb('local');
        $this->remote->files[self::LIVE] = 'live';

        $tester = $this->execute('db:push', [], ['n']);

        self::assertStringContainsString('Aborted.', $tester->getDisplay());
        self::assertSame('live', $this->remote->files[self::LIVE]);
    }

    public function testPushRefusesWithoutLocalDatabase(): void
    {
        $this->remote->files[self::LIVE] = 'live';

        $tester = $this->execute('db:push', ['--yes' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertSame('live', $this->remote->files[self::LIVE]);
    }

    public function testPullWritesToTheLocalPathWhenItDiffersFromTheRemote(): void
    {
        $this->writeIni("pfdk_db = var/data_prod.db\npfdk_db_local = var/data_dev.db\n");
        mkdir("{$this->dir}/var");
        file_put_contents("{$this->dir}/var/data_dev.db", 'old dev');
        $this->remote->files['var/data_prod.db'] = 'live';

        $tester = $this->execute('db:pull');

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame('live', file_get_contents("{$this->dir}/var/data_dev.db"));
        self::assertSame('old dev', $this->onlyFile('var/data_dev.db.bak-local-*'));
        self::assertFileDoesNotExist("{$this->dir}/var/data_prod.db");
    }

    public function testPushUploadsTheLocalFileToTheRemotePath(): void
    {
        $this->writeIni("pfdk_db = var/data_prod.db\npfdk_db_local = var/data_dev.db\n");
        mkdir("{$this->dir}/var");
        file_put_contents("{$this->dir}/var/data_dev.db", 'dev');
        file_put_contents("{$this->dir}/var/data_prod.db", 'must not be pushed');
        $this->remote->files['var/data_prod.db'] = 'live';

        $tester = $this->execute('db:push', ['--yes' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame('dev', $this->remote->files['var/data_prod.db']);
        self::assertSame('live', $this->onlyFile('backups/database-pre-push-*.db'));
    }

    public function testPushComplainsAboutTheMissingLocalFile(): void
    {
        $this->writeIni("pfdk_db = var/data_prod.db\npfdk_db_local = var/data_dev.db\n");

        $tester = $this->execute('db:push', ['--yes' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('var/data_dev.db', $tester->getDisplay());
    }

    public function testPullFailsClearlyWhenTheLocalPathIsADirectory(): void
    {
        $this->remote->files[self::LIVE] = 'live';
        mkdir("{$this->dir}/var/database.db", 0777, true);

        $tester = $this->execute('db:pull');

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('Could not write', $tester->getDisplay());
        self::assertStringNotContainsString('Pulled live database', $tester->getDisplay());
    }

    public function testBackupRemovesOldestBackupsBeyondTheLimit(): void
    {
        $this->writeIni("pfdk_db = var/database.db\npfdk_backups_keep = 2\n");
        $this->remote->files[self::LIVE] = 'live';
        $this->seedBackups(['database-a.db' => 1000, 'database-b.db' => 2000, 'database-c.db' => 3000, 'database-d.db' => 4000]);

        $tester = $this->execute('backup');

        self::assertSame(2, count(glob("{$this->dir}/backups/database-*.db")));
        self::assertFileExists("{$this->dir}/backups/database-d.db");
        self::assertFileDoesNotExist("{$this->dir}/backups/database-a.db");
        self::assertStringContainsString('Removed old backup: database-a.db', $tester->getDisplay());
    }

    public function testRotationCanBeDisabled(): void
    {
        $this->writeIni("pfdk_db = var/database.db\npfdk_backups_keep = 0\n");
        $this->remote->files[self::LIVE] = 'live';
        $this->seedBackups(['database-a.db' => 1000, 'database-b.db' => 2000]);

        $this->execute('backup');

        self::assertSame(3, count(glob("{$this->dir}/backups/database-*.db")));
    }

    public function testRotationLeavesForeignFilesAlone(): void
    {
        $this->writeIni("pfdk_db = var/database.db\npfdk_backups_keep = 1\n");
        $this->remote->files[self::LIVE] = 'live';
        $this->seedBackups(['database-a.db' => 1000, 'notes.txt' => 500]);

        $this->execute('backup');

        self::assertFileExists("{$this->dir}/backups/notes.txt");
    }

    public function testPullAndPushRotateToo(): void
    {
        $this->writeIni("pfdk_db = var/database.db\npfdk_backups_keep = 1\n");
        $this->writeLocalDb('local');
        $this->remote->files[self::LIVE] = 'live';
        $this->seedBackups(['database-a.db' => 1000]);

        $this->execute('db:pull');
        self::assertFileDoesNotExist("{$this->dir}/backups/database-a.db");

        $this->seedBackups(['database-b.db' => 1000]);
        $this->execute('db:push', ['--yes' => true]);
        self::assertFileDoesNotExist("{$this->dir}/backups/database-b.db");
    }

    public function testUnreachableServerAbortsBackup(): void
    {
        $this->remote->unreachable = true;

        $this->expectExceptionMessage('connection refused');
        $this->execute('backup');
    }

    public function testUnreachableServerLeavesLocalDatabaseUntouchedOnPull(): void
    {
        $local = $this->writeLocalDb('precious');
        $this->remote->unreachable = true;

        try {
            $this->execute('db:pull');
            self::fail('Expected the pull to abort');
        } catch (\RuntimeException) {
            self::assertSame('precious', file_get_contents($local));
        }
    }

    public function testUnreachableServerAbortsPushBeforeUploading(): void
    {
        $this->writeLocalDb('local');
        $this->remote->unreachable = true;

        $this->expectExceptionMessage('connection refused');
        $this->execute('db:push', ['--yes' => true]);
    }

    /** @param array<string,int> $filesWithModifiedTime */
    private function seedBackups(array $filesWithModifiedTime): void
    {
        is_dir("{$this->dir}/backups") || mkdir("{$this->dir}/backups", 0777, true);
        foreach ($filesWithModifiedTime as $name => $time) {
            touch("{$this->dir}/backups/$name", $time);
        }
    }

    /**
     * @param array<string,mixed> $arguments
     * @param list<string>        $inputs
     */
    private function execute(string $name, array $arguments = [], array $inputs = []): CommandTester
    {
        $tester = new CommandTester(ApplicationFactory::create(fn () => $this->remote)->find($name));
        $tester->setInputs($inputs);
        $tester->execute($arguments, ['interactive' => $inputs !== []]);

        return $tester;
    }

    private function onlyFile(string $pattern): string
    {
        $files = glob("{$this->dir}/$pattern");
        self::assertCount(1, $files, "Expected exactly one file matching $pattern");

        return (string) file_get_contents($files[0]);
    }
}
