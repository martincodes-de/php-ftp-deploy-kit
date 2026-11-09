<?php
declare(strict_types=1);

namespace Pfdk\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('exec', 'Run a command in the project folder, through DDEV when it runs (use `--` before the command)')]
final class ExecCommand extends BaseCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->addArgument('command_line', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'The command, e.g. -- composer install --no-dev');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->config($input, $output);
        $command = implode(' ', $input->getArgument('command_line'));

        return $this->runInProject($config, $command, $output);
    }
}
