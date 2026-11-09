<?php
declare(strict_types=1);

namespace Pfdk\Tests\Console;

use Pfdk\Console\ApplicationFactory;
use Pfdk\Tests\Support\InMemoryRemoteFiles;
use Pfdk\Tests\Support\ProjectTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class CheckAndGuardTest extends ProjectTestCase
{
    public function testCheckReportsAWorkingConfig(): void
    {
        $this->writeIni();

        $tester = $this->cli('check');

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('curl protocols', $tester->getDisplay());
        self::assertStringContainsString('OK: ftp:// works', $tester->getDisplay());
    }

    public function testCheckWithoutConfigShowsOnlyTheGeneralReport(): void
    {
        $tester = $this->cli('check');

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Only the general report', $tester->getDisplay());
    }

    public function testCheckFailsForABrokenConfigInsteadOfSkippingIt(): void
    {
        file_put_contents("{$this->dir}/deploy.ini", "remote = \"nonsense\"\n");

        $tester = $this->cli('check');

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('ftp://host/path', $tester->getDisplay());
    }

    public function testCheckFailsForAnUnsupportedProtocol(): void
    {
        $this->writeRemote('gopher://host/path');

        $tester = $this->cli('check');

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString("Unsupported scheme 'gopher://'", $tester->getDisplay());
    }

    public function testRemoteCommandsStopBeforeAnyTransferWhenTheProtocolIsUnsupported(): void
    {
        $this->writeRemote('gopher://host/path');
        $remote = new InMemoryRemoteFiles(['var/database.db' => 'live']);

        $this->expectExceptionMessage('pfdk check');
        $this->cli('backup', fn () => $remote);
    }

    public function testDeployStopsWhenTheProtocolIsUnsupported(): void
    {
        $this->writeRemote('gopher://host/path');

        $this->expectExceptionMessage("Unsupported scheme 'gopher://'");
        $this->cli('deploy');
    }

    public function testPlainFtpIsAnnounced(): void
    {
        $this->writeIni();

        $display = $this->cli('backup', fn () => new InMemoryRemoteFiles())->getDisplay();

        self::assertStringContainsString('ftp:// sends your password unencrypted', $display);
    }

    public function testTlsAndLocalRemotesAreNotWarnedAbout(): void
    {
        foreach (['ftps://u:p@host/app', 'file:///tmp'] as $remote) {
            $this->writeRemote($remote);

            $display = $this->cli('backup', fn () => new InMemoryRemoteFiles())->getDisplay();

            self::assertStringNotContainsString('unencrypted', $display, $remote);
        }
    }

    private function writeRemote(string $remote): void
    {
        file_put_contents("{$this->dir}/deploy.ini", "remote = \"$remote\"\npfdk_db = var/database.db\n");
    }

    private function cli(string $name, ?callable $remoteFactory = null): CommandTester
    {
        $tester = new CommandTester(ApplicationFactory::create($remoteFactory)->find($name));
        $tester->execute([]);

        return $tester;
    }
}
