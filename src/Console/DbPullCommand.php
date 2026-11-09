<?php
declare(strict_types=1);

namespace Pfdk\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('db:pull', 'Replace the local database with the live one (the local one is backed up first)')]
final class DbPullCommand extends BaseCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->config($input, $output);
        $stamp = $this->stamp();
        $pulled = $config->backupFile("database-pulled-$stamp.db");

        // Download first: a failed pull must never touch the local database.
        if (!$this->remote($config, $output)->download($config->requireDb(), $pulled)) {
            $output->writeln('<error>Download failed or the live database is empty; local database left untouched.</error>');

            return self::FAILURE;
        }

        $local = $config->localDbFile();
        if (is_file($local)) {
            copy($local, "$local.bak-local-$stamp");
            $output->writeln("Local database backed up as $local.bak-local-$stamp");
        }
        is_dir(dirname($local)) || mkdir(dirname($local), 0777, true);
        copy($pulled, $local);

        $output->writeln("Pulled live database into $local (copy: $pulled)");
        $this->pruneBackups($config, $output);

        return self::SUCCESS;
    }
}
