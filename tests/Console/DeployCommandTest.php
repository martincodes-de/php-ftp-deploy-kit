<?php
declare(strict_types=1);

namespace Pfdk\Tests\Console;

use Pfdk\Console\ApplicationFactory;
use Pfdk\Console\DeployCommand;
use Pfdk\Tests\Support\InMemoryRemoteFiles;
use Pfdk\Tests\Support\ProjectTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;

final class DeployCommandTest extends ProjectTestCase
{
    public function testRunsTheDeploymentToolWithTheIni(): void
    {
        $this->writeFakeDg(0);
        $this->writeIni();

        $tester = $this->deploy([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('args: ' . $this->dir . '/deploy.ini', $tester->getDisplay());
        self::assertStringNotContainsString('--test', $tester->getDisplay());
    }

    public function testForwardsTheSectionToDg(): void
    {
        $this->writeFakeDg(0);
        file_put_contents("{$this->dir}/deploy.ini", "[staging]\nremote = \"ftps://u:p@s.example.org/app\"\n[production]\nremote = \"ftps://u:p@example.org/app\"\n");

        $tester = $this->deploy(['--section' => 'production']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('--section production', $tester->getDisplay());
    }

    public function testDryRunPassesTestFlag(): void
    {
        $this->writeFakeDg(0);
        $this->writeIni();

        self::assertStringContainsString('--test', $this->deploy(['--dry' => true])->getDisplay());
    }

    public function testFailingToolFailsTheCommand(): void
    {
        $this->writeFakeDg(3);
        $this->writeIni();

        self::assertSame(1, $this->deploy([])->getStatusCode());
    }

    public function testFailedPullStopsBeforeDeploying(): void
    {
        $this->writeFakeDg(0);
        $this->writeIni();

        $tester = $this->deploy(['--pull' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringNotContainsString('args:', $tester->getDisplay());
    }

    /** The regression test for dg living next to pfdk in Composer's vendor folder, not inside pfdk. */
    public function testFindsTheDgThatComposerInstalledWithPfdk(): void
    {
        mkdir("{$this->dir}/site");
        file_put_contents("{$this->dir}/site/index.php", '<?php echo 1;');
        mkdir("{$this->dir}/target");
        file_put_contents("{$this->dir}/deploy.ini", "remote = \"file://{$this->dir}/target\"\nlocal = site\n");

        $tester = $this->deploy(['--dry' => true]);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Finished at', $tester->getDisplay());
    }

    public function testOffersTheMigrationFirstAndRunsItWhenAccepted(): void
    {
        $remote = $this->symfonyProject('echo migrated >> var/database.db');

        $tester = $this->deploy([], ['y', 'y'], $remote);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertSame("live\nmigrated\n", $remote->files['var/database.db']);
        self::assertStringContainsString('args:', $tester->getDisplay());
    }

    public function testDeclinedMigrationIsSkipped(): void
    {
        $remote = $this->symfonyProject('echo migrated >> var/database.db');

        $tester = $this->deploy([], ['n'], $remote);

        self::assertSame("live\n", $remote->files['var/database.db']);
        self::assertStringContainsString('args:', $tester->getDisplay());
    }

    public function testFailedMigrationStopsTheDeploy(): void
    {
        $remote = $this->symfonyProject('exit 3');

        $tester = $this->deploy([], ['y', 'y'], $remote);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringNotContainsString('args:', $tester->getDisplay());
    }

    public function testNoMigrationQuestionForDryRunsOrWithoutInteraction(): void
    {
        $remote = $this->symfonyProject('echo migrated >> var/database.db');

        $dry = $this->deploy(['--dry' => true], ['n'], $remote);
        $quiet = $this->deploy([], [], $remote);

        self::assertStringNotContainsString('migrate', $dry->getDisplay());
        self::assertStringNotContainsString('migrate', $quiet->getDisplay());
        self::assertSame("live\n", $remote->files['var/database.db']);
    }

    public function testFinallyStepsRunAfterASuccessfulDeploy(): void
    {
        $this->writeFakeDg(0);
        $this->writeIni("pfdk_finally[] = \"echo one >> finally.log\"\npfdk_finally[] = \"echo two >> finally.log\"\n");

        $tester = $this->deploy([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame("one\ntwo\n", file_get_contents("{$this->dir}/finally.log"));
    }

    public function testFinallyStepsRunAfterAFailedDeployAndTheDeployExitCodeWins(): void
    {
        $this->writeFakeDg(3);
        $this->writeIni("pfdk_finally[] = \"echo restored >> finally.log\"\n");

        $tester = $this->deploy([]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertSame("restored\n", file_get_contents("{$this->dir}/finally.log"));
    }

    public function testFinallyStepsRunAfterADryRun(): void
    {
        $this->writeFakeDg(0);
        $this->writeIni("pfdk_finally[] = \"echo restored >> finally.log\"\n");

        $tester = $this->deploy(['--dry' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertFileExists("{$this->dir}/finally.log");
    }

    public function testFailingFinallyStepIsShownFailsTheCommandAndDoesNotSkipTheNext(): void
    {
        $this->writeFakeDg(0);
        $this->writeIni("pfdk_finally[] = \"exit 4\"\npfdk_finally[] = \"echo next >> finally.log\"\n");

        $tester = $this->deploy([]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('pfdk_finally step failed: exit 4', $tester->getDisplay());
        self::assertFileExists("{$this->dir}/finally.log");
    }

    public function testFailingFinallyStepDoesNotHideAFailedDeploy(): void
    {
        $this->writeFakeDg(3);
        $this->writeIni("pfdk_finally[] = \"exit 4\"\n");

        $tester = $this->deploy([]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('args:', $tester->getDisplay());
        self::assertStringContainsString('pfdk_finally step failed', $tester->getDisplay());
    }

    public function testFinallyStepsDoNotRunWhenNothingWasDeployed(): void
    {
        $this->writeFakeDg(0);
        $this->writeIni("pfdk_finally[] = \"echo restored >> finally.log\"\n");

        $this->deploy(['--pull' => true]);

        self::assertFileDoesNotExist("{$this->dir}/finally.log");
    }

    public function testFinallyDoesNotRunWhenTheMigrationBeforeTheDeployFails(): void
    {
        $this->writeFakeDg(0);
        $this->writeIni("pfdk_db = var/database.db\npfdk_type = symfony\npfdk_migrate_command = \"exit 3\"\npfdk_finally[] = \"echo restored >> finally.log\"\n");

        $tester = $this->deploy([], ['y', 'y'], new InMemoryRemoteFiles(['var/database.db' => "live\n"]));

        self::assertSame(1, $tester->getStatusCode());
        self::assertFileDoesNotExist("{$this->dir}/finally.log");
    }

    public function testFinallyRunsEvenWhenDgCannotConnect(): void
    {
        $this->writeFakeDg(1);
        $this->writeIni("pfdk_finally[] = \"echo restored >> finally.log\"\n");

        $this->deploy([]);

        self::assertFileExists("{$this->dir}/finally.log");
    }

    public function testSigtermWhileDgRunsIsPassedOnAndFinallyStillRuns(): void
    {
        if (!function_exists('pcntl_signal') || !function_exists('posix_kill')) {
            self::markTestSkipped('needs pcntl and posix');
        }
        mkdir("{$this->dir}/vendor/dg/ftp-deployment", 0777, true);
        file_put_contents("{$this->dir}/vendor/dg/ftp-deployment/deployment", "<?php\nposix_kill(posix_getppid(), SIGTERM);\nsleep(3);\nfile_put_contents('survived.log', 'x');\n");
        $this->writeIni("pfdk_finally[] = \"echo restored >> finally.log\"\n");

        $application = ApplicationFactory::create();
        $application->setAutoExit(false);
        $output = new BufferedOutput();
        $status = $application->run(new ArrayInput(['command' => 'deploy']), $output);

        self::assertSame(1, $status);
        self::assertFileExists("{$this->dir}/finally.log");
        self::assertFileDoesNotExist("{$this->dir}/survived.log");
    }

    public function testSignalOutsideTheDeploymentExitsWithTheSignalCode(): void
    {
        if (!function_exists('pcntl_signal')) {
            self::markTestSkipped('needs pcntl');
        }
        $command = new DeployCommand();

        self::assertSame(128 + SIGTERM, $command->handleSignal(SIGTERM));
        self::assertSame([SIGINT, SIGTERM, SIGHUP], $command->getSubscribedSignals());
    }

    public function testSectionCanHaveItsOwnFinallySteps(): void
    {
        $this->writeFakeDg(0);
        file_put_contents("{$this->dir}/deploy.ini", "[staging]\nremote = \"ftps://u:p@s.example.org/app\"\npfdk_finally[] = \"echo staging >> finally.log\"\n[production]\nremote = \"ftps://u:p@example.org/app\"\n");

        $this->deploy(['--section' => 'staging']);
        $this->deploy(['--section' => 'production']);

        self::assertSame("staging\n", file_get_contents("{$this->dir}/finally.log"));
    }

    private function symfonyProject(string $migration): InMemoryRemoteFiles
    {
        $this->writeFakeDg(0);
        $this->writeIni("pfdk_db = var/database.db\npfdk_type = symfony\npfdk_migrate_command = \"$migration\"\n");

        return new InMemoryRemoteFiles(['var/database.db' => "live\n"]);
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string>        $inputs
     */
    private function deploy(array $options, array $inputs = [], ?InMemoryRemoteFiles $remote = null): CommandTester
    {
        $factory = $remote !== null ? static fn () => $remote : null;
        $tester = new CommandTester(ApplicationFactory::create($factory)->find('deploy'));
        $tester->setInputs($inputs);
        $tester->execute($options, ['interactive' => $inputs !== []]);

        return $tester;
    }

    /** The project's own vendor folder wins over the dg installed with pfdk, which lets tests inject a fake. */
    private function writeFakeDg(int $exitCode): void
    {
        mkdir("{$this->dir}/vendor/dg/ftp-deployment", 0777, true);
        file_put_contents("{$this->dir}/vendor/dg/ftp-deployment/deployment", "<?php\necho 'args: ' . implode(' ', array_slice(\$argv, 1));\nexit($exitCode);\n");
    }
}
