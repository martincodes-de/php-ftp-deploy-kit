<?php
declare(strict_types=1);

namespace Pfdk\Tests\Console;

use Pfdk\Console\ApplicationFactory;
use Pfdk\Tests\Support\ProjectTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ExecCommandTest extends ProjectTestCase
{
    public function testRunsTheCommandOnTheHostInTheProjectFolder(): void
    {
        $this->writeIni();

        $tester = $this->exec(['pwd']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString(basename($this->dir), $tester->getDisplay());
        self::assertStringNotContainsString('DDEV', $tester->getDisplay());
    }

    public function testPassesTheExitCodeThrough(): void
    {
        $this->writeIni();

        self::assertSame(3, $this->exec(['exit', '3'])->getStatusCode());
    }

    public function testRunsThroughDdevWhenItIsRunning(): void
    {
        $log = $this->installFakeDdev();
        $this->writeIni();

        $tester = $this->exec(['echo', 'hi']);

        self::assertStringContainsString('Running through DDEV', $tester->getDisplay());
        self::assertStringContainsString('ddev exec echo hi', (string) file_get_contents($log));
    }

    public function testDdevCanBeSwitchedOff(): void
    {
        $log = $this->installFakeDdev();
        $this->writeIni("pfdk_ddev = off\n");

        $tester = $this->exec(['echo', 'hi']);

        self::assertStringNotContainsString('DDEV', $tester->getDisplay());
        self::assertFileDoesNotExist($log);
    }

    public function testStoppedDdevFallsBackToTheHost(): void
    {
        $this->installFakeDdev('stopped');
        $this->writeIni();

        self::assertStringNotContainsString('Running through DDEV', $this->exec(['echo', 'hi'])->getDisplay());
    }

    /** @param list<string> $command */
    private function exec(array $command): CommandTester
    {
        $tester = new CommandTester(ApplicationFactory::create()->find('exec'));
        $tester->execute(['command_line' => $command]);

        return $tester;
    }
}
