<?php
declare(strict_types=1);

namespace Pfdk\Tests\Console;

use Pfdk\Config\DeployConfig;
use Pfdk\Console\ApplicationFactory;
use Pfdk\Tests\Support\ProjectTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Tester\CommandTester;

final class InitCommandTest extends ProjectTestCase
{
    public function testListsThePresets(): void
    {
        $display = $this->init([])->getDisplay();

        self::assertStringContainsString('symfony-sqlite', $display);
        self::assertStringContainsString('static', $display);
    }

    public function testCopiesPresetFilesIntoTheCurrentFolder(): void
    {
        $tester = $this->init(['preset' => 'symfony-sqlite']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertFileExists("{$this->dir}/deploy.ini.example");
        self::assertFileExists("{$this->dir}/env-prod-flag");
    }

    public function testNeverOverwritesExistingFiles(): void
    {
        file_put_contents("{$this->dir}/env-prod-flag", 'mine');

        $tester = $this->init(['preset' => 'symfony-sqlite']);

        self::assertStringContainsString('skipped (exists): env-prod-flag', $tester->getDisplay());
        self::assertSame('mine', file_get_contents("{$this->dir}/env-prod-flag"));
    }

    public function testPresetPathsFollowTheFolderName(): void
    {
        mkdir("{$this->dir}/deploy");
        chdir("{$this->dir}/deploy");

        $this->init(['preset' => 'symfony-sqlite']);
        $ini = (string) file_get_contents("{$this->dir}/deploy/deploy.ini.example");

        self::assertStringContainsString('php deploy/vendor/bin/pfdk backup -c deploy/deploy.ini', $ini);
        self::assertStringNotContainsString('ftp-deployment', $ini);
    }

    public function testToolNameIsNotRewritten(): void
    {
        $presets = $this->makePresets(['plain' => ['a.ini' => "; dg/ftp-deployment runs from ftp-deployment/\n"]]);
        $work = $this->workFolder('deploy');

        $this->initFrom($presets, ['preset' => 'plain']);

        self::assertSame("; dg/ftp-deployment runs from deploy/\n", file_get_contents("$work/a.ini"));
    }

    public function testExtendingPresetIncludesBaseFilesAndItsOwnFilesWin(): void
    {
        $presets = $this->makePresets([
            'base' => ['x.txt' => 'base x', 'y.txt' => 'base y'],
            'child' => ['extends' => "base\n", 'y.txt' => 'child y'],
        ]);
        $work = $this->workFolder('work');

        $this->initFrom($presets, ['preset' => 'child']);

        self::assertSame('base x', file_get_contents("$work/x.txt"));
        self::assertSame('child y', file_get_contents("$work/y.txt"));
        self::assertFileDoesNotExist("$work/extends");
    }

    public function testSnippetsReplaceMarkersAndAreNotCopied(): void
    {
        $presets = $this->makePresets([
            'base' => ['f.ini' => "a\n; @pfdk:one\nb\n; @pfdk:two\nc\n"],
            'child' => ['extends' => 'base', 'one.snippet' => "X1\nX2\n"],
        ]);
        $work = $this->workFolder('work');

        $this->initFrom($presets, ['preset' => 'child']);

        self::assertSame("a\nX1\nX2\nb\nc\n", file_get_contents("$work/f.ini"));
        self::assertFileDoesNotExist("$work/one.snippet");
    }

    public function testMarkersDisappearWhenNothingExtendsThePreset(): void
    {
        $presets = $this->makePresets(['base' => ['f.ini' => "a\n; @pfdk:one\nb\n"]]);
        $work = $this->workFolder('work');

        $this->initFrom($presets, ['preset' => 'base']);

        self::assertSame("a\nb\n", file_get_contents("$work/f.ini"));
    }

    public function testMissingBasePresetIsReported(): void
    {
        $presets = $this->makePresets(['child' => ['extends' => 'ghost']]);
        $this->workFolder('work');

        $tester = $this->initFrom($presets, ['preset' => 'child']);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString("Unknown preset 'ghost'", $tester->getDisplay());
    }

    public function testExtendsCyclesAreRejected(): void
    {
        $presets = $this->makePresets(['a' => ['extends' => 'b'], 'b' => ['extends' => 'a']]);
        $this->workFolder('work');

        $tester = $this->initFrom($presets, ['preset' => 'a']);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('extends itself', $tester->getDisplay());
    }

    public function testSqlitePresetIsTheSymfonyPresetPlusDatabaseSteps(): void
    {
        $this->init(['preset' => 'symfony-sqlite']);
        $raw = (string) file_get_contents("{$this->dir}/deploy.ini.example");

        self::assertFileExists("{$this->dir}/env-prod-flag");
        self::assertFileDoesNotExist("{$this->dir}/settings.snippet");
        self::assertStringContainsString('pfdk_db = var/database.db', $raw);
        self::assertStringNotContainsString('@pfdk:', $raw);
        self::assertStringContainsString('rm -rf public/assets', $raw);
    }

    public function testSymfonyPresetHasNoDatabaseStepsAndAssetsAreOptIn(): void
    {
        $this->init(['preset' => 'symfony']);
        $raw = (string) file_get_contents("{$this->dir}/deploy.ini.example");
        $ini = parse_ini_file("{$this->dir}/deploy.ini.example");

        self::assertArrayNotHasKey('pfdk_db', $ini);
        self::assertStringNotContainsString('pfdk backup', $raw);
        self::assertStringContainsString('; before[] = "local: php ' . basename($this->dir), $raw);
        self::assertStringNotContainsString('asset-map:compile', implode("\n", $ini['before']));
        self::assertStringStartsWith('ftps://', $ini['remote']);
        self::assertStringContainsString('composer install --no-dev', $ini['before'][0]);
    }

    public function testConventionalFolderNameStaysUnchanged(): void
    {
        mkdir("{$this->dir}/ftp-deployment");
        chdir("{$this->dir}/ftp-deployment");

        $this->init(['preset' => 'symfony-sqlite']);

        self::assertStringContainsString('php ftp-deployment/vendor/bin/pfdk backup', (string) file_get_contents("{$this->dir}/ftp-deployment/deploy.ini.example"));
    }

    public function testSymfonyPresetIgnoresTestCacheAndOffersTheSymfonyTypeAsComment(): void
    {
        $this->init(['preset' => 'symfony-sqlite']);
        $raw = (string) file_get_contents("{$this->dir}/deploy.ini.example");

        self::assertStringContainsString("\t.phpunit.result.cache", $raw);
        self::assertStringContainsString('; pfdk_type = symfony', $raw);
        self::assertArrayNotHasKey('pfdk_type', parse_ini_file("{$this->dir}/deploy.ini.example"));
    }

    #[DataProvider('invalidNames')]
    public function testRejectsUnknownOrUnsafePresetNames(string $name): void
    {
        $tester = $this->init(['preset' => $name]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('Unknown preset', $tester->getDisplay());
    }

    /** @return iterable<string,array{string}> */
    public static function invalidNames(): iterable
    {
        yield 'unknown' => ['nope'];
        yield 'path traversal' => ['../src'];
    }

    #[DataProvider('presetNames')]
    public function testEveryPresetIniIsUsableByTheConfigParser(string $preset): void
    {
        $this->init(['preset' => $preset]);
        rename("{$this->dir}/deploy.ini.example", "{$this->dir}/deploy.ini");

        self::assertSame('HOST', DeployConfig::load("{$this->dir}/deploy.ini")->host);
    }

    public function testSymfonyPresetBuildsThroughPfdkExecAndRecommendsTls(): void
    {
        $this->init(['preset' => 'symfony-sqlite']);
        $raw = (string) file_get_contents("{$this->dir}/deploy.ini.example");

        $folder = basename($this->dir);

        self::assertStringContainsString("pfdk exec -c $folder/deploy.ini -- APP_ENV=prod composer install --no-dev", $raw);
        self::assertStringContainsString('remote = "ftps://', $raw);
    }

    public function testSymfonyPresetBacksUpBeforeRemovingDevDependencies(): void
    {
        $this->init(['preset' => 'symfony-sqlite']);
        $jobs = parse_ini_file("{$this->dir}/deploy.ini.example")['before'];

        $backup = array_keys(array_filter($jobs, fn (string $job) => str_contains($job, 'pfdk backup')));
        $noDev = array_keys(array_filter($jobs, fn (string $job) => str_contains($job, '--no-dev')));

        self::assertNotEmpty($backup);
        self::assertLessThan($noDev[0], $backup[0]);
    }

    /** @return iterable<string,array{string}> */
    public static function presetNames(): iterable
    {
        foreach (glob(__DIR__ . '/../../presets/*', GLOB_ONLYDIR) as $dir) {
            yield basename($dir) => [basename($dir)];
        }
    }

    /** @param array<string,mixed> $arguments */
    private function init(array $arguments): CommandTester
    {
        return $this->initFrom(null, $arguments);
    }

    /** @param array<string,mixed> $arguments */
    private function initFrom(?string $presetsDir, array $arguments): CommandTester
    {
        $tester = new CommandTester(ApplicationFactory::create(null, $presetsDir)->find('init'));
        $tester->execute($arguments);

        return $tester;
    }

    /**
     * @param array<string,array<string,string>> $presets preset name => file name => content
     *
     * @return string the presets folder
     */
    private function makePresets(array $presets): string
    {
        $root = "{$this->dir}/presets";
        foreach ($presets as $name => $files) {
            mkdir("$root/$name", 0777, true);
            foreach ($files as $file => $content) {
                file_put_contents("$root/$name/$file", $content);
            }
        }

        return $root;
    }

    private function workFolder(string $name): string
    {
        mkdir("{$this->dir}/$name");
        chdir("{$this->dir}/$name");

        return "{$this->dir}/$name";
    }
}
