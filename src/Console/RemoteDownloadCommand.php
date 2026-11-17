<?php
declare(strict_types=1);

namespace Pfdk\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand('remote:download', 'Download a file from the remote path to a local file')]
final class RemoteDownloadCommand extends BaseCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->addArgument('remote', InputArgument::REQUIRED, 'Path below the remote path');
        $this->addArgument('local', InputArgument::REQUIRED, 'Local file (relative to the project folder)');
        $this->addOption('yes', 'y', InputOption::VALUE_NONE, 'Replace an existing local file without asking');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->config($input, $output);
        $path = $this->remoteArgument((string) $input->getArgument('remote'));
        $local = $this->projectPath($config, (string) $input->getArgument('local'));

        $question = new ConfirmationQuestion("Replace the existing $local? [y/N] ", false);
        if (is_file($local) && !$input->getOption('yes') && !$this->getHelper('question')->ask($input, $output, $question)) {
            $output->writeln('Aborted.');

            return self::SUCCESS;
        }

        // Into a temporary file first: a failed download must never touch the existing local file.
        $temporary = "$local.pfdk-download";
        if (!$this->remote($config, $output)->download($path, $temporary)) {
            $output->writeln("<error>$path is missing or empty on the server; nothing was written.</error>");

            return self::FAILURE;
        }

        if (!@rename($temporary, $local)) {
            @unlink($temporary);
            $output->writeln("<error>Could not write $local.</error>");

            return self::FAILURE;
        }

        $output->writeln("Downloaded to $local.");

        return self::SUCCESS;
    }
}
