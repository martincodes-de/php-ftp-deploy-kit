<?php
declare(strict_types=1);

namespace Pfdk\Console;

use Pfdk\Config\DeployConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

#[AsCommand('deploy', 'Run dg/ftp-deployment with the deploy.ini; all steps live in its before[]/after[] jobs')]
final class DeployCommand extends BaseCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->addOption('dry', null, InputOption::VALUE_NONE, 'Preview only (dg --test)')
            ->addOption('pull', null, InputOption::VALUE_NONE, 'Run git pull --ff-only first');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->config($input);
        $this->requireEnvironment($config, forDeploy: true);
        $this->warnIfPlainFtp($config, $output);

        $tool = $this->findDeploymentTool($config);
        if ($tool === null) {
            $output->writeln('<error>dg/ftp-deployment not found; run composer install or set pfdk_tool in the ini.</error>');

            return self::FAILURE;
        }

        if ($input->getOption('pull') && !$this->runProcess(['git', 'pull', '--ff-only'], $output)) {
            return self::FAILURE;
        }

        $command = [PHP_BINARY, $tool, $config->file];
        if ($input->getOption('dry')) {
            $command[] = '--test';
        }

        return $this->runProcess($command, $output) ? self::SUCCESS : self::FAILURE;
    }

    private function findDeploymentTool(DeployConfig $config): ?string
    {
        // An explicit pfdk_tool must not silently fall back to another binary.
        if ($config->tool !== null) {
            return is_file($config->tool) ? $config->tool : null;
        }

        // The project's own vendor folder wins, so its pinned dg version is the one that deploys.
        $candidates = [
            $config->projectDir() . '/vendor/dg/ftp-deployment/deployment',
            __DIR__ . '/../../vendor/dg/ftp-deployment/deployment',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /** @param list<string> $command */
    private function runProcess(array $command, OutputInterface $output): bool
    {
        // A deployment can outlive Process' default 60 s limit.
        $process = new Process($command, null, null, null, null);
        $process->run(static fn (string $type, string $buffer) => $output->write($buffer));

        return $process->isSuccessful();
    }
}
