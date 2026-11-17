<?php
declare(strict_types=1);

namespace Pfdk\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('remote:mkdir', 'Create a folder (and missing parents) below the remote path')]
final class RemoteMkdirCommand extends BaseCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->addArgument('path', InputArgument::REQUIRED, 'Folder below the remote path, e.g. var/database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->config($input, $output);
        $path = $this->remoteArgument((string) $input->getArgument('path'));

        $this->remote($config, $output)->createFolder($path);
        $output->writeln("Folder $path is there.");

        return self::SUCCESS;
    }
}
