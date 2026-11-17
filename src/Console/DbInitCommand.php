<?php
declare(strict_types=1);

namespace Pfdk\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('db:init', 'Prepare a fresh server: create the database folder and upload the local database (never overwrites a live one)')]
final class DbInitCommand extends BaseCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->databaseDisabled()) {
            $output->writeln('<error>Refused: the database is switched off (PFDK_NO_DB / deploy --no-db).</error>');

            return self::FAILURE;
        }

        $config = $this->config($input, $output);
        $db = $config->requireDb();
        $local = $config->localDbFile();

        if (!is_file($local)) {
            $output->writeln("<error>Local database not found: $local</error>");

            return self::FAILURE;
        }

        $remote = $this->remote($config, $output);
        $remote->createFolder(dirname($db) === '.' ? '' : dirname($db));

        $probe = (string) tempnam(sys_get_temp_dir(), 'pfdk');
        $exists = $remote->download($db, $probe);
        @unlink($probe);
        if ($exists) {
            $output->writeln("<error>A live database already exists on {$config->host}. db:init never overwrites it; use db:push to replace it.</error>");

            return self::FAILURE;
        }

        $remote->upload($local, $db);
        $output->writeln("Server is ready: folder created and local database uploaded as $db.");

        return self::SUCCESS;
    }
}
