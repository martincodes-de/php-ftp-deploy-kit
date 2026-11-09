<?php
declare(strict_types=1);

namespace Pfdk\Tests\Console;

use Pfdk\Console\ApplicationFactory;
use Pfdk\Tests\Support\InMemoryRemoteFiles;
use Pfdk\Tests\Support\ProjectTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class MigrateCommandTest extends ProjectTestCase
{
    private const LIVE = 'var/database.db';

    private InMemoryRemoteFiles $remote;

    protected function setUp(): void
    {
        parent::setUp();
        $this->remote = new InMemoryRemoteFiles();
    }

    public function testPullsMigratesAndPushes(): void
    {
        $this->iniWithMigration('echo migrated >> var/database.db');
        $this->remote->files[self::LIVE] = "live\n";

        $tester = $this->migrate(['--yes' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame("live\nmigrated\n", $this->remote->files[self::LIVE]);
        self::assertSame("live\n", $this->onlyFile('backups/database-pre-push-*.db'));
    }

    public function testRunsTheDefaultMigrationCommandInTheProjectFolder(): void
    {
        $this->writeIni("pfdk_db = var/database.db\npfdk_type = symfony\n");
        $this->remote->files[self::LIVE] = 'live';
        mkdir("{$this->dir}/bin");
        file_put_contents("{$this->dir}/bin/console", "<?php\nfile_put_contents('var/database.db', implode(' ', array_slice(\$argv, 1)));\n");

        $this->migrate(['--yes' => true]);

        self::assertSame('doctrine:migrations:migrate --no-interaction', $this->remote->files[self::LIVE]);
    }

    public function testRequiresTheSymfonyType(): void
    {
        $this->writeIni();
        $this->remote->files[self::LIVE] = 'live';

        $tester = $this->migrate(['--yes' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('pfdk_type = symfony', $tester->getDisplay());
        self::assertSame('live', $this->remote->files[self::LIVE]);
    }

    public function testFailedPullSkipsTheMigration(): void
    {
        $this->iniWithMigration('touch migration-ran');

        $tester = $this->migrate(['--yes' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertFileDoesNotExist("{$this->dir}/migration-ran");
        self::assertStringContainsString('Pull failed', $tester->getDisplay());
    }

    public function testFailedMigrationPushesNothing(): void
    {
        $this->iniWithMigration('exit 3');
        $this->remote->files[self::LIVE] = 'live';

        $tester = $this->migrate(['--yes' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertSame('live', $this->remote->files[self::LIVE]);
        self::assertStringContainsString('Migration failed', $tester->getDisplay());
    }

    public function testPushCanBeDeclined(): void
    {
        $this->iniWithMigration('echo migrated >> var/database.db');
        $this->remote->files[self::LIVE] = "live\n";

        $tester = $this->migrate([], ['n']);

        self::assertStringContainsString('Aborted.', $tester->getDisplay());
        self::assertSame("live\n", $this->remote->files[self::LIVE]);
    }

    private function iniWithMigration(string $command): void
    {
        $this->writeIni("pfdk_db = var/database.db\npfdk_type = symfony\npfdk_migrate_command = \"$command\"\n");
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string>        $inputs
     */
    private function migrate(array $options, array $inputs = []): CommandTester
    {
        $tester = new CommandTester(ApplicationFactory::create(fn () => $this->remote)->find('db:migrate'));
        $tester->setInputs($inputs);
        $tester->execute($options, ['interactive' => $inputs !== []]);

        return $tester;
    }

    private function onlyFile(string $pattern): string
    {
        $files = glob("{$this->dir}/$pattern");
        self::assertCount(1, $files);

        return (string) file_get_contents($files[0]);
    }
}
