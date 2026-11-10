<?php
declare(strict_types=1);

namespace Pfdk\Console;

use Pfdk\Config\ConfigLocator;
use Pfdk\Config\DeployConfig;
use Pfdk\Config\SectionRequired;
use Pfdk\Environment\Ddev;
use Pfdk\Environment\Requirements;
use Pfdk\Remote\CurlRemoteFiles;
use Pfdk\Remote\RemoteFiles;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
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
        $this->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to the deploy ini (default: found automatically)');
        $this->addOption('section', 's', InputOption::VALUE_REQUIRED, 'Deploy target, if the ini has several sections');
    }

    protected function config(InputInterface $input, OutputInterface $output): DeployConfig
    {
        $file = $this->configFile($input, $output);

        try {
            return DeployConfig::load($file, $input->getOption('section'));
        } catch (SectionRequired $e) {
            if (!$input->isInteractive()) {
                throw $e;
            }

            return DeployConfig::load($file, $this->choose($input, $output, 'Which target?', $e->sections));
        }
    }

    private function configFile(InputInterface $input, OutputInterface $output): string
    {
        $folder = (string) getcwd();
        $path = $input->getOption('config');
        if ($path !== null) {
            return preg_match('~^(/|[A-Za-z]:)~', $path) === 1 ? $path : "$folder/$path";
        }

        $candidates = ConfigLocator::candidates($folder);
        $relative = array_map(static fn (string $file): string => substr($file, strlen($folder) + 1), $candidates);

        if ($candidates === []) {
            return "$folder/deploy.ini";
        }
        if (count($candidates) === 1) {
            $relative[0] === 'deploy.ini' || $output->writeln("Using {$relative[0]}");

            return $candidates[0];
        }
        if (!$input->isInteractive()) {
            throw new \RuntimeException("Several ini files found; pick one with --config:\n" . implode("\n", $relative));
        }

        return "$folder/" . $this->choose($input, $output, 'Which ini?', $relative);
    }

    /** @param list<string> $options */
    private function choose(InputInterface $input, OutputInterface $output, string $question, array $options): string
    {
        return $this->getHelper('question')->ask($input, $output, new ChoiceQuestion($question, $options));
    }

    /** @param array<string,mixed> $arguments */
    protected function subInput(InputInterface $parent, array $arguments): ArrayInput
    {
        $input = new ArrayInput($arguments);
        $input->setInteractive($parent->isInteractive());
        if ($parent instanceof StreamableInputInterface) {
            $input->setStream($parent->getStream());
        }

        return $input;
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
            throw new \RuntimeException(implode("\n", $problems));
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

        // Exactly the names pfdk writes (stamp() format), so foreign backups such as database-pulled-<other>.db are safe.
        $files = array_filter(
            glob(dirname($config->backupFile('x')) . '/database-*.db') ?: [],
            static fn (string $file): bool => preg_match('/^database-(pulled-|pre-push-)?\d{8}-\d{6}\.db$/', basename($file)) === 1,
        );
        usort($files, fn (string $a, string $b) => filemtime($b) <=> filemtime($a) ?: strcmp($b, $a));

        foreach (array_slice($files, $config->backupsKeep) as $old) {
            unlink($old);
            $output->writeln('Removed old backup: ' . basename($old));
        }
    }
}
