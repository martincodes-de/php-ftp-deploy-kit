<?php
declare(strict_types=1);

namespace Pfdk\Tests\Console;

use Pfdk\Console\ApplicationFactory;
use Pfdk\Tests\Support\InMemoryRemoteFiles;
use Pfdk\Tests\Support\ProjectTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * PFDK_NO_DB=1 is what `deploy --no-db` hands to dg: the steps of the deploy must then keep their hands off the database.
 */
final class NoDbTest extends ProjectTestCase
{
    private const LIVE = 'var/database.db';

    private InMemoryRemoteFiles $remote;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeIni("pfdk_db = var/database.db\npfdk_type = symfony\n");
        $this->remote = new InMemoryRemoteFiles([self::LIVE => 'live']);
        putenv('PFDK_NO_DB=1');
    }

    protected function tearDown(): void
    {
        putenv('PFDK_NO_DB');
        parent::tearDown();
    }

    public function testBackupDownloadsNothingAndSucceedsSoTheDeployCarriesOn(): void
    {
        $tester = $this->execute('backup');

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Skipped', $tester->getDisplay());
        self::assertSame([], glob("{$this->dir}/backups/*") ?: []);
    }

    public function testPushRefusesAndLeavesTheLiveDatabaseAlone(): void
    {
        $this->writeLocalDb('local');

        $tester = $this->execute('db:push', ['--yes' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('Refused', $tester->getDisplay());
        self::assertSame('live', $this->remote->files[self::LIVE]);
    }

    public function testPullRefusesAndLeavesTheLocalDatabaseAlone(): void
    {
        $local = $this->writeLocalDb('precious');

        $tester = $this->execute('db:pull');

        self::assertSame(1, $tester->getStatusCode());
        self::assertSame('precious', file_get_contents($local));
        self::assertSame([], glob("{$this->dir}/var/*.bak-*") ?: []);
    }

    public function testInitRefusesAndCreatesNothingOnTheServer(): void
    {
        $this->remote->files = [];
        $this->writeLocalDb('local');

        $tester = $this->execute('db:init');

        self::assertSame(1, $tester->getStatusCode());
        self::assertSame([], $this->remote->files);
        self::assertSame([], $this->remote->folders);
    }

    public function testMigrateRefusesBeforeItPullsAnything(): void
    {
        $tester = $this->execute('db:migrate', ['--yes' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertSame('live', $this->remote->files[self::LIVE]);
        self::assertSame([], glob("{$this->dir}/backups/*") ?: []);
    }

    public function testWithoutTheSwitchEverythingWorksAsBefore(): void
    {
        putenv('PFDK_NO_DB');

        $tester = $this->execute('backup');

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Backup saved', $tester->getDisplay());
    }

    public function testOnlyTheValueOneSwitchesTheDatabaseOff(): void
    {
        putenv('PFDK_NO_DB=0');

        self::assertStringContainsString('Backup saved', $this->execute('backup')->getDisplay());
    }

    /** @param array<string,mixed> $arguments */
    private function execute(string $name, array $arguments = []): CommandTester
    {
        $tester = new CommandTester(ApplicationFactory::create(fn () => $this->remote)->find($name));
        $tester->execute($arguments, ['interactive' => false]);

        return $tester;
    }
}
