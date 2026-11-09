<?php
declare(strict_types=1);

namespace Pfdk\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('init', 'Copy a preset (deploy.ini.example and helper files) into the current folder')]
final class InitCommand extends BaseCommand
{
    private const PRESETS_DIR = __DIR__ . '/../../presets';

    protected function configure(): void
    {
        $this->addArgument('preset', InputArgument::OPTIONAL, 'Preset name; omit to list the available ones');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $preset = $input->getArgument('preset');

        if ($preset === null) {
            $output->writeln(array_map('basename', glob(self::PRESETS_DIR . '/*', GLOB_ONLYDIR)));

            return self::SUCCESS;
        }

        // Plain names only, so a preset can't point outside the presets folder.
        if (preg_match('/^[a-z0-9-]+$/', $preset) !== 1 || !is_dir(self::PRESETS_DIR . "/$preset")) {
            $output->writeln("<error>Unknown preset '$preset'. Run `pfdk init` to list the presets.</error>");

            return self::FAILURE;
        }

        foreach (glob(self::PRESETS_DIR . "/$preset/*") as $source) {
            $name = basename($source);

            // The project owns these files after the first init, so never overwrite them.
            if (is_file($name)) {
                $output->writeln("skipped (exists): $name");
                continue;
            }
            copy($source, $name);
            $output->writeln("created: $name");
        }
        $output->writeln('Next: copy deploy.ini.example to deploy.ini (gitignored) and fill in the connection.');

        return self::SUCCESS;
    }
}
