<?php
declare(strict_types=1);

namespace Pfdk\Console;

use Pfdk\Config\DeployConfig;
use Pfdk\Remote\RemoteFiles;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

#[AsCommand('init', 'Set up a deployment: copy a preset, create deploy.ini and ignore it in git')]
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
        $this->addArgument('preset', InputArgument::OPTIONAL, 'Preset name or path to your own preset folder; omit to choose');
        $this->addOption('public', null, InputOption::VALUE_REQUIRED, 'Folder your web server serves, for presets that use it (default: public)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $preset = $input->getArgument('preset') ?? $this->askForPreset($input, $output);
        if ($preset === null) {
            return self::SUCCESS;
        }

        try {
            $files = $this->presetFiles($this->presetDir($preset));
        } catch (\InvalidArgumentException $e) {
            $output->writeln("<error>{$e->getMessage()} Run `pfdk init` to list the presets.</error>");

            return self::FAILURE;
        }

        try {
            $public = $this->publicDir($input, $output, $files);
        } catch (\InvalidArgumentException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");

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
            $content = str_replace('@public@', $public, $content);
            file_put_contents($name, preg_replace('~^; @pfdk:[a-z0-9-]+[ \t]*\r?\n~m', '', $content));
            $output->writeln("created: $name");
        }

        if (is_file('deploy.ini.example') && !is_file('deploy.ini')) {
            copy('deploy.ini.example', 'deploy.ini');
            $output->writeln('created: deploy.ini (gitignored, put your connection in here)');
        }
        $this->ignoreInGit($output);
        $output->writeln('Next: edit deploy.ini, then run `pfdk deploy --dry`.');

        return self::SUCCESS;
    }

    /**
     * The folder the web server serves. Only asked when the preset uses the @public@ placeholder.
     *
     * @param array<string,string> $files
     */
    private function publicDir(InputInterface $input, OutputInterface $output, array $files): string
    {
        $default = 'public';
        $usesPlaceholder = array_filter($files, static fn (string $content): bool => str_contains($content, '@public@')) !== [];
        $public = $input->getOption('public');

        if ($public === null && $usesPlaceholder && $input->isInteractive()) {
            $public = $this->getHelper('question')->ask($input, $output, new Question("Folder your web server serves [$default]: ", $default));
        }

        $public = trim((string) ($public ?? $default), '/');
        if (preg_match('~^[A-Za-z0-9._-]+(/[A-Za-z0-9._-]+)*$~', $public) !== 1 || in_array('..', explode('/', $public), true)) {
            throw new \InvalidArgumentException("Invalid public folder '$public'. Use a relative path such as public or web.");
        }

        return $public;
    }

    private function askForPreset(InputInterface $input, OutputInterface $output): ?string
    {
        $presets = array_map('basename', glob($this->presetsDir . '/*', GLOB_ONLYDIR));
        if (!$input->isInteractive()) {
            $output->writeln($presets);

            return null;
        }

        return $this->getHelper('question')->ask($input, $output, new ChoiceQuestion('Which preset?', $presets));
    }

    /** A path is your own preset folder, a plain name is one of the bundled presets. */
    private function presetDir(string $preset, ?string $relativeTo = null): string
    {
        $dir = false;
        if (str_contains($preset, '/') || str_contains($preset, '\\')) {
            $isAbsolute = preg_match('~^(/|[A-Za-z]:)~', $preset) === 1;
            $dir = realpath($isAbsolute || $relativeTo === null ? $preset : "$relativeTo/$preset");
        } elseif (preg_match('/^[a-z0-9-]+$/', $preset) === 1) {
            $dir = realpath($this->presetsDir . "/$preset");
        }

        return $dir !== false && is_dir($dir) ? $dir : throw new \InvalidArgumentException("Unknown preset '$preset'.");
    }

    /**
     * Files of a preset: its base preset first (`extends`), then its own files, with `<marker>.snippet`
     * files replacing the matching `; @pfdk:<marker>` lines of the base.
     *
     * @param list<string> $chain preset folders already being resolved, to stop cycles
     *
     * @return array<string,string> file name => content
     */
    private function presetFiles(string $dir, array $chain = []): array
    {
        if (in_array($dir, $chain, true)) {
            throw new \InvalidArgumentException("Preset '" . basename($dir) . "' extends itself (" . implode(' -> ', array_map('basename', [...$chain, $dir])) . ').');
        }

        $files = [];
        if (is_file("$dir/extends")) {
            $base = $this->presetDir(trim((string) file_get_contents("$dir/extends")), $dir);
            $files = $this->presetFiles($base, [...$chain, $dir]);
        }

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

    private function ignoreInGit(OutputInterface $output): void
    {
        $here = (string) getcwd();
        // Usually pfdk lives in a subfolder of the project; if this folder is the git project itself, ignore at its root.
        $project = file_exists("$here/.git") ? $here : dirname($here);
        $prefix = $project === $here ? '' : '/' . basename($here);
        $block = "###> pfdk ###\n$prefix/deploy.ini\n$prefix/backups/\n$prefix/deploy.log\n$prefix/vendor/\n###< pfdk ###\n";

        if (!file_exists("$project/.git")) {
            $output->writeln("Add this to your .gitignore:\n$block");

            return;
        }

        $gitignore = "$project/.gitignore";
        $existing = is_file($gitignore) ? (string) file_get_contents($gitignore) : '';
        if (str_contains($existing, '###> pfdk ###')) {
            return;
        }

        file_put_contents($gitignore, ($existing === '' ? '' : rtrim($existing, "\r\n") . "\n\n") . $block);
        $output->writeln('updated: ' . ($project === $here ? '' : '../') . '.gitignore');
    }
}
