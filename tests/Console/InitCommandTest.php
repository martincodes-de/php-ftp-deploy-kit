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
