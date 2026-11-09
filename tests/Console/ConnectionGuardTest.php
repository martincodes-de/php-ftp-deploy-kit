<?php
declare(strict_types=1);

namespace Pfdk\Tests\Console;

use Pfdk\Console\ApplicationFactory;
use Pfdk\Tests\Support\InMemoryRemoteFiles;
use Pfdk\Tests\Support\ProjectTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ConnectionGuardTest extends ProjectTestCase
{
    public function testRemoteCommandsStopBeforeAnyTransferWhenTheProtocolIsUnsupported(): void
    {
        $this->writeRemote('gopher://host/path');
        $remote = new InMemoryRemoteFiles(['var/database.db' => 'live']);

        $this->expectExceptionMessage("Unsupported scheme 'gopher://'");
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

    public function testThereIsNoCheckCommand(): void
    {
        self::assertFalse(ApplicationFactory::create()->has('check'));
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
