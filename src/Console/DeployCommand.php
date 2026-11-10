<?php
declare(strict_types=1);

namespace Pfdk\Console;

use Composer\InstalledVersions;
use Pfdk\Config\DeployConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Process\Process;

#[AsCommand('deploy', 'Run dg/ftp-deployment with the deploy.ini; all steps live in its before[]/after[] jobs')]
final class DeployCommand extends BaseCommand implements SignalableCommandInterface
{
    private ?Process $deployment = null;

    protected function configure(): void
    {
        parent::configure();
        $this->addOption('dry', null, InputOption::VALUE_NONE, 'Preview only (dg --test)')
            ->addOption('pull', null, InputOption::VALUE_NONE, 'Run git pull --ff-only first');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->config($input, $output);
        $this->requireEnvironment($config, forDeploy: true);
        $this->warnIfPlainFtp($config, $output);

        $tool = $this->findDeploymentTool($config);
        if ($tool === null) {
            $output->writeln('<error>dg/ftp-deployment not found; run composer install in the folder where pfdk is installed.</error>');

            return self::FAILURE;
        }

        if ($input->getOption('pull') && !$this->runProcess(['git', 'pull', '--ff-only'], $output)) {
            return self::FAILURE;
        }

        if ($this->shouldMigrateFirst($config, $input, $output) && !$this->migrate($config, $input, $output)) {
            return self::FAILURE;
        }

        $command = [PHP_BINARY, $tool, $config->file];
        if ($config->section !== null) {
            array_push($command, '--section', $config->section);
        }
        if ($input->getOption('dry')) {
            $command[] = '--test';
        }

        try {
            $deployed = $this->runProcess($command, $output, isDeployment: true);
        } finally {
            $cleaned = $this->runFinally($config, $output);
        }

        return $deployed && $cleaned ? self::SUCCESS : self::FAILURE;
    }

    /** Runs every step even if one fails; a dry run too, because dg runs before[] steps then as well. */
    private function runFinally(DeployConfig $config, OutputInterface $output): bool
    {
        $allSucceeded = true;
        foreach ($config->finally as $step) {
            $output->writeln("<comment>pfdk_finally: $step</comment>");
            if ($this->runInProject($config, $step, $output, viaDdev: false) !== 0) {
                $output->writeln("<error>pfdk_finally step failed: $step</error>");
                $allSucceeded = false;
            }
        }

        return $allSucceeded;
    }

    /** Only offered for Symfony projects with a database, and never for a dry run, which must not change anything. */
    private function shouldMigrateFirst(DeployConfig $config, InputInterface $input, OutputInterface $output): bool
    {
        if (!$input->isInteractive() || $input->getOption('dry') || $config->type !== 'symfony' || $config->db === null) {
            return false;
        }

        $question = new ConfirmationQuestion('Pull, migrate and push the database before deploying? [y/N] ', false);

        return (bool) $this->getHelper('question')->ask($input, $output, $question);
    }

    private function migrate(DeployConfig $config, InputInterface $input, OutputInterface $output): bool
    {
        $arguments = ['--config' => $config->file];
        if ($config->section !== null) {
            $arguments['--section'] = $config->section;
        }

        return $this->getApplication()->find('db:migrate')->run($this->subInput($input, $arguments), $output) === self::SUCCESS;
    }

    private function findDeploymentTool(DeployConfig $config): ?string
    {
        // The project's own copy wins, so its pinned dg version is the one that deploys.
        // Otherwise use the copy Composer installed next to pfdk, wherever that vendor folder is.
        $candidates = [
            $config->projectDir() . '/vendor/dg/ftp-deployment/deployment',
            (InstalledVersions::getInstallPath('dg/ftp-deployment') ?? '') . '/deployment',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /** @param list<string> $command */
    private function runProcess(array $command, OutputInterface $output, bool $isDeployment = false): bool
    {
        // A deployment can outlive Process' default 60 s limit.
        $process = new Process($command, null, null, null, null);
        $this->deployment = $isDeployment ? $process : null;
        try {
            $process->run(static fn (string $type, string $buffer) => $output->write($buffer));
        } finally {
            $this->deployment = null;
        }

        return $process->isSuccessful();
    }

    /** Without pcntl nothing is subscribed, so an interrupted deploy then skips pfdk_finally[]. */
    public function getSubscribedSignals(): array
    {
        return function_exists('pcntl_signal') ? [\SIGINT, \SIGTERM, \SIGHUP] : [];
    }

    /**
     * While dg runs, pfdk keeps waiting for it so pfdk_finally[] still runs afterwards. The terminal already sent
     * Ctrl-C to dg; other signals only reached pfdk, so they are passed on. In every other phase pfdk exits.
     */
    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        if ($this->deployment === null) {
            return 128 + $signal;
        }
        if ($signal !== \SIGINT) {
            $this->deployment->signal($signal);
        }

        return false;
    }
}
