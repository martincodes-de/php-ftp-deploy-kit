<?php
declare(strict_types=1);

namespace Pfdk\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand('db:push', 'Overwrite the live database with the local one (the live one is backed up first)')]
final class DbPushCommand extends BaseCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->addOption('yes', 'y', InputOption::VALUE_NONE, 'Do not ask for confirmation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->config($input);
        $local = $config->localDbFile();

        if (!is_file($local)) {
            $output->writeln("<error>Local database not found: $local</error>");

            return self::FAILURE;
        }

        $question = new ConfirmationQuestion("Overwrite the LIVE database on {$config->host}? [y/N] ", false);
        if (!$input->getOption('yes') && !$this->getHelper('question')->ask($input, $output, $question)) {
            $output->writeln('Aborted.');

            return self::SUCCESS;
        }

        $remote = $this->remote($config);
        $backup = $config->backupFile("database-pre-push-{$this->stamp()}.db");
        $output->writeln($remote->download($config->requireDb(), $backup) ? "Live database backed up as $backup" : 'No live database yet; this is the first push.');

        $remote->upload($local, $config->requireDb());
        $output->writeln('Local database pushed.');

        return self::SUCCESS;
    }
}
