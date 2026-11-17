<?php
declare(strict_types=1);

namespace Pfdk\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand('remote:upload', 'Upload a file to a path below the remote path (an existing file is replaced)')]
final class RemoteUploadCommand extends BaseCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->addArgument('local', InputArgument::REQUIRED, 'Local file (relative to the project folder)');
        $this->addArgument('remote', InputArgument::REQUIRED, 'Path below the remote path');
        $this->addOption('yes', 'y', InputOption::VALUE_NONE, 'Do not ask for confirmation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->config($input, $output);
        $local = $this->projectPath($config, (string) $input->getArgument('local'));
        $path = $this->remoteArgument((string) $input->getArgument('remote'));

        if (!is_file($local)) {
            $output->writeln("<error>Local file not found: $local</error>");

            return self::FAILURE;
        }

        $question = new ConfirmationQuestion("Upload $local to $path on {$config->host}? An existing file there is replaced. [y/N] ", false);
        if (!$input->getOption('yes') && !$this->getHelper('question')->ask($input, $output, $question)) {
            $output->writeln('Aborted.');

            return self::SUCCESS;
        }

        $this->remote($config, $output)->upload($local, $path);
        $output->writeln("Uploaded to $path.");

        return self::SUCCESS;
    }
}
