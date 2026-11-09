<?php
declare(strict_types=1);

namespace Pfdk\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('backup', 'Download the live database into the backup folder')]
final class BackupCommand extends BaseCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->config($input);
        $target = $config->backupFile("database-{$this->stamp()}.db");

        if (!$this->remote($config)->download($config->requireDb(), $target)) {
            $output->writeln('<comment>No live database found; nothing backed up.</comment>');

            return self::SUCCESS;
        }

        $output->writeln("Backup saved: $target");
        $this->pruneBackups($config, $output);

        return self::SUCCESS;
    }
}
