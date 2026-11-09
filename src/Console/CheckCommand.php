<?php
declare(strict_types=1);

namespace Pfdk\Console;

use Pfdk\Config\ConfigNotFound;
use Pfdk\Config\DeployConfig;
use Pfdk\Environment\Requirements;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('check', 'Show what this PHP supports and whether the configured protocol works')]
final class CheckCommand extends BaseCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $requirements = Requirements::fromRuntime();
        foreach ($requirements->describe() as $name => $value) {
            $output->writeln(sprintf('%-15s %s', $name, $value));
        }

        try {
            $config = $this->config($input);
        } catch (ConfigNotFound $e) {
            $output->writeln("\n{$e->getMessage()} Only the general report is shown.");

            return self::SUCCESS;
        } catch (\RuntimeException $e) {
            $output->writeln("\n<error>{$e->getMessage()}</error>");

            return self::FAILURE;
        }

        return $this->verdict($config, $requirements, $output);
    }

    private function verdict(DeployConfig $config, Requirements $requirements, OutputInterface $output): int
    {
        $problems = $requirements->problems($config->scheme, forDeploy: true);
        if ($problems === []) {
            $output->writeln("\n<info>OK: {$config->scheme}:// works with this PHP (including deploy).</info>");

            return self::SUCCESS;
        }

        foreach ($problems as $problem) {
            $output->writeln("<error>$problem</error>");
        }

        return self::FAILURE;
    }
}
