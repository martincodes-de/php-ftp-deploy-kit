<?php
declare(strict_types=1);

namespace Pfdk\Console;

use Pfdk\Config\DeployConfig;
use Pfdk\Environment\Ddev;
use Pfdk\Environment\Requirements;
use Pfdk\Remote\CurlRemoteFiles;
use Pfdk\Remote\RemoteFiles;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

abstract class BaseCommand extends Command
{
    /** @var (callable(DeployConfig):RemoteFiles)|null swapped for an in-memory fake in tests */
    private $remoteFactory;

    /** @param (callable(DeployConfig):RemoteFiles)|null $remoteFactory */
    public function __construct(?callable $remoteFactory = null)
    {
        parent::__construct();
        $this->remoteFactory = $remoteFactory;
    }

    protected function configure(): void
    {
        $this->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to deploy.ini', 'deploy.ini');
        $this->addOption('section', 's', InputOption::VALUE_REQUIRED, 'Deploy target, if the ini has several sections');
    }

    protected function config(InputInterface $input): DeployConfig
    {
        $path = (string) $input->getOption('config');
        $section = $input->getOption('section');

        return DeployConfig::load(preg_match('~^(/|[A-Za-z]:)~', $path) === 1 ? $path : getcwd() . '/' . $path, $section);
    }

    protected function remote(DeployConfig $config, OutputInterface $output): RemoteFiles
    {
        $this->requireEnvironment($config, forDeploy: false);
        $this->warnIfPlainFtp($config, $output);

        return $this->remoteFactory !== null ? ($this->remoteFactory)($config) : new CurlRemoteFiles($config);
    }

    /** Runs a shell command in the project folder, through DDEV when it runs there; returns the exit code. */
    protected function runInProject(DeployConfig $config, string $command, OutputInterface $output, bool $viaDdev = true): int
    {
        $dir = $config->projectDir();

        if ($viaDdev && $config->ddev && Ddev::isRunning($dir)) {
            $output->writeln('<comment>Running through DDEV.</comment>');
            $process = new Process(['ddev', 'exec', $command], $dir, null, null, null);
        } else {
            $process = Process::fromShellCommandline($command, $dir, null, null, null);
        }

        $process->run(static fn (string $type, string $buffer) => $output->write($buffer));

        return $process->getExitCode() ?? 1;
    }

    protected function warnIfPlainFtp(DeployConfig $config, OutputInterface $output): void
    {
        if ($config->scheme === 'ftp') {
            $output->writeln('<comment>Warning: ftp:// sends your password unencrypted. Use ftps:// if your host supports it.</comment>');
        }
    }

    protected function requireEnvironment(DeployConfig $config, bool $forDeploy): void
    {
        $problems = Requirements::fromRuntime()->problems($config->scheme, $forDeploy);
        if ($problems !== []) {
            throw new \RuntimeException(implode("\n", $problems) . "\nRun `pfdk check` to see what this PHP supports.");
        }
    }

    protected function stamp(): string
    {
        return date('Ymd-His');
    }

    protected function pruneBackups(DeployConfig $config, OutputInterface $output): void
    {
        if ($config->backupsKeep === 0) {
            return;
        }

        // Only our own file names, so anything else a user keeps in that folder is safe.
        $files = glob(dirname($config->backupFile('x')) . '/database-*.db') ?: [];
        usort($files, fn (string $a, string $b) => filemtime($b) <=> filemtime($a) ?: strcmp($b, $a));

        foreach (array_slice($files, $config->backupsKeep) as $old) {
            unlink($old);
            $output->writeln('Removed old backup: ' . basename($old));
        }
    }
}
