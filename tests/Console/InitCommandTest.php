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
        $preset = __DIR__ . '/../../presets/tmp-tool-name';
        mkdir($preset);
        file_put_contents("$preset/a.ini", "; dg/ftp-deployment runs from ftp-deployment/\n");
        mkdir("{$this->dir}/deploy");
        chdir("{$this->dir}/deploy");

        try {
            $this->init(['preset' => 'tmp-tool-name']);
        } finally {
            unlink("$preset/a.ini");
            rmdir($preset);
        }

        self::assertSame("; dg/ftp-deployment runs from deploy/\n", file_get_contents("{$this->dir}/deploy/a.ini"));
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
        $tester = new CommandTester(ApplicationFactory::create()->find('init'));
        $tester->execute($arguments);

        return $tester;
    }
}
