<?php
declare(strict_types=1);

namespace Pfdk\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StreamableInputInterface;
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
        $config = $this->config($input);
        if ($config->type !== 'symfony') {
            $output->writeln('<error>db:migrate needs pfdk_type = symfony in the ini.</error>');

            return self::FAILURE;
        }

        $arguments = ['--config' => $input->getOption('config')];
        if ($config->section !== null) {
            $arguments['--section'] = $config->section;
        }

        if ($this->getApplication()->find('db:pull')->run($this->subInput($input, $arguments), $output) !== self::SUCCESS) {
            $output->writeln('<error>Pull failed; nothing was migrated or pushed.</error>');

            return self::FAILURE;
        }

        // A custom command is run exactly as written, so users stay in control of any wrapping.
        $explicit = $config->migrateCommand !== null;
        if ($this->runInProject($config, $config->migrateCommand ?? self::DEFAULT_MIGRATION, $output, viaDdev: !$explicit) !== 0) {
            $output->writeln('<error>Migration failed; the live database was not touched. The pulled copy is in the backup folder.</error>');

            return self::FAILURE;
        }

        if ($input->getOption('yes')) {
            $arguments['--yes'] = true;
        }

        return $this->getApplication()->find('db:push')->run($this->subInput($input, $arguments), $output);
    }

    /** @param array<string,mixed> $arguments */
    private function subInput(InputInterface $parent, array $arguments): ArrayInput
    {
        $input = new ArrayInput($arguments);
        $input->setInteractive($parent->isInteractive());
        if ($parent instanceof StreamableInputInterface) {
            $input->setStream($parent->getStream());
        }

        return $input;
    }
}
