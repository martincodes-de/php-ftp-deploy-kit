<?php
declare(strict_types=1);

namespace Pfdk\Console;

use Pfdk\Remote\RemoteFiles;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand('remote:rm', 'Delete a file or folder below the remote path (-r: with everything in it)')]
final class RemoteRmCommand extends BaseCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->addArgument('path', InputArgument::REQUIRED, 'File or folder below the remote path');
        $this->addOption('recursive', 'r', InputOption::VALUE_NONE, 'Delete a folder with everything in it');
        $this->addOption('yes', 'y', InputOption::VALUE_NONE, 'Do not ask for confirmation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->config($input, $output);
        $path = $this->remoteArgument((string) $input->getArgument('path'));
        $recursive = (bool) $input->getOption('recursive');

        $what = $recursive ? "$path and everything in it" : $path;
        $question = new ConfirmationQuestion("Delete $what on {$config->host}? [y/N] ", false);
        if (!$input->getOption('yes') && !$this->getHelper('question')->ask($input, $output, $question)) {
            $output->writeln('Aborted.');

            return self::SUCCESS;
        }

        $remote = $this->remote($config, $output);
        $removed = $recursive ? $this->removeTree($remote, $path) : $remote->deleteFile($path) || $remote->deleteFolder($path);
        if (!$removed) {
            $output->writeln("<error>Could not delete $path: it does not exist" . ($recursive ? '.' : ', or it is a folder that is not empty (use -r).') . '</error>');

            return self::FAILURE;
        }

        $output->writeln("Deleted $path.");

        return self::SUCCESS;
    }

    /** NLST cannot tell files from folders, so every entry is tried as a file first and only then entered as a folder. */
    private function removeTree(RemoteFiles $remote, string $path): bool
    {
        if ($remote->deleteFile($path)) {
            return true;
        }

        try {
            $children = $remote->names($path);
        } catch (\RuntimeException) {
            return false;
        }

        foreach ($children as $child) {
            if (!$this->removeTree($remote, "$path/$child")) {
                return false;
            }
        }

        return $remote->deleteFolder($path);
    }
}
