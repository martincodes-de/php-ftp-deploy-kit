<?php
declare(strict_types=1);

namespace Pfdk\Console;

use Pfdk\Config\DeployConfig;
use Pfdk\Remote\RemoteFiles;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('init', 'Copy a preset (deploy.ini.example and helper files) into the current folder')]
final class InitCommand extends BaseCommand
{
    private readonly string $presetsDir;

    /** @param (callable(DeployConfig):RemoteFiles)|null $remoteFactory */
    public function __construct(?callable $remoteFactory = null, ?string $presetsDir = null)
    {
        parent::__construct($remoteFactory);
        $this->presetsDir = $presetsDir ?? __DIR__ . '/../../presets';
    }

    protected function configure(): void
    {
        $this->addArgument('preset', InputArgument::OPTIONAL, 'Preset name; omit to list the available ones');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $preset = $input->getArgument('preset');

        if ($preset === null) {
            $output->writeln(array_map('basename', glob($this->presetsDir . '/*', GLOB_ONLYDIR)));

            return self::SUCCESS;
        }

        try {
            $files = $this->presetFiles($preset);
        } catch (\InvalidArgumentException $e) {
            $output->writeln("<error>{$e->getMessage()} Run `pfdk init` to list the presets.</error>");

            return self::FAILURE;
        }

        foreach ($files as $name => $content) {
            // The project owns these files after the first init, so never overwrite them.
            if (is_file($name)) {
                $output->writeln("skipped (exists): $name");
                continue;
            }
            // Not after "dg/": that is the name of the deployment tool, not our folder.
            $content = preg_replace('~(?<!dg/)ftp-deployment~', basename((string) getcwd()), $content);
            file_put_contents($name, preg_replace('~^; @pfdk:[a-z0-9-]+[ \t]*\r?\n~m', '', $content));
            $output->writeln("created: $name");
        }
        $output->writeln('Next: copy deploy.ini.example to deploy.ini (gitignored) and fill in the connection.');

        return self::SUCCESS;
    }

    /**
     * Files of a preset: its base preset first (`extends`), then its own files, with `<marker>.snippet`
     * files replacing the matching `; @pfdk:<marker>` lines of the base.
     *
     * @param list<string> $chain presets already being resolved, to stop cycles
     *
     * @return array<string,string> file name => content
     */
    private function presetFiles(string $name, array $chain = []): array
    {
        // Plain names only, so a preset can't point outside the presets folder.
        if (preg_match('/^[a-z0-9-]+$/', $name) !== 1 || !is_dir($dir = $this->presetsDir . "/$name")) {
            throw new \InvalidArgumentException("Unknown preset '$name'.");
        }
        if (in_array($name, $chain, true)) {
            throw new \InvalidArgumentException("Preset '$name' extends itself (" . implode(' -> ', [...$chain, $name]) . ').');
        }

        $files = is_file("$dir/extends") ? $this->presetFiles(trim((string) file_get_contents("$dir/extends")), [...$chain, $name]) : [];

        foreach (glob("$dir/*") as $path) {
            $file = basename($path);
            if ($file !== 'extends' && !str_ends_with($file, '.snippet')) {
                $files[$file] = (string) file_get_contents($path);
            }
        }

        foreach (glob("$dir/*.snippet") as $snippet) {
            $marker = preg_quote(basename($snippet, '.snippet'), '~');
            $content = rtrim((string) file_get_contents($snippet));
            $files = preg_replace_callback_array(["~^; @pfdk:{$marker}[ \t]*\r?$~m" => static fn (): string => $content], $files);
        }

        return $files;
    }
}
