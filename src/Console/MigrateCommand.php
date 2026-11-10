<?php
declare(strict_types=1);

namespace Pfdk\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('db:migrate', 'Symfony: pull the live database, run the migrations locally, push it back')]
final class MigrateCommand extends BaseCommand
{
    private const DEFAULT_MIGRATION = 'php bin/console doctrine:migrations:migrate --no-interaction';

    protected function configure(): void
    {
        parent::configure();
        $this->addOption('yes', 'y', InputOption::VALUE_NONE, 'Do not ask before overwriting the live database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->config($input, $output);
        if ($config->type !== 'symfony') {
            $output->writeln('<error>db:migrate needs pfdk_type = symfony in the ini.</error>');

            return self::FAILURE;
        }

        // The resolved file and section, so the sub-commands never ask or discover again.
        $arguments = ['--config' => $config->file];
        if ($config->section !== null) {
            $arguments['--section'] = $config->section;
        }

        if ($this->getApplication()->find('db:pull')->run($this->subInput($input, $arguments), $output) !== self::SUCCESS) {
            $output->writeln('<error>Pull failed; nothing was migrated or pushed.</error>');

            return self::FAILURE;
        }

        $before = md5_file($config->localDbFile());

        // A custom command is run exactly as written, so users stay in control of any wrapping.
        $explicit = $config->migrateCommand !== null;
        if ($this->runInProject($config, $config->migrateCommand ?? self::DEFAULT_MIGRATION, $output, viaDdev: !$explicit) !== 0) {
            $output->writeln('<error>Migration failed; the live database was not touched. The pulled copy is in the backup folder.</error>');

            return self::FAILURE;
        }

        if (md5_file($config->localDbFile()) === $before) {
            $output->writeln('<comment>The migration did not change ' . $config->localDbFile() . ', so nothing was pushed. If migrations were pending, the app\'s DATABASE_URL does not point at this file (see pfdk_db_local in the README).</comment>');

            return self::SUCCESS;
        }

        if ($input->getOption('yes')) {
            $arguments['--yes'] = true;
        }

        return $this->getApplication()->find('db:push')->run($this->subInput($input, $arguments), $output);
    }
}
