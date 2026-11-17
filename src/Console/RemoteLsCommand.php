<?php
declare(strict_types=1);

namespace Pfdk\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('remote:ls', 'List a folder below the remote path')]
final class RemoteLsCommand extends BaseCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->addArgument('path', InputArgument::OPTIONAL, 'Folder below the remote path (default: the remote path itself)', '');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->config($input, $output);
        $path = $this->remoteArgument((string) $input->getArgument('path'), allowEmpty: true);

        $names = $this->remote($config, $output)->names($path);
        $output->writeln($names === [] ? '<comment>(empty)</comment>' : $names);

        return self::SUCCESS;
    }
}
