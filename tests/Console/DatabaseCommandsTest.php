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
