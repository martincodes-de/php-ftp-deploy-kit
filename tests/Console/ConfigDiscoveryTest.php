<?php
declare(strict_types=1);

namespace Pfdk\Tests\Console;

use Pfdk\Config\SectionRequired;
use Pfdk\Console\ApplicationFactory;
use Pfdk\Tests\Support\InMemoryRemoteFiles;
use Pfdk\Tests\Support\ProjectTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ConfigDiscoveryTest extends ProjectTestCase
{
    /** @var list<string> hosts the commands connected to */
    private array $hosts = [];

    public function testUsesTheOnlyIniInASubfolderAndSaysSo(): void
    {
        $this->iniAt('ftp-deployment/deploy.ini', 'one.example.org');

        $tester = $this->backup();

        self::assertStringContainsString('Using ftp-deployment/deploy.ini', $tester->getDisplay());
        self::assertSame(['one.example.org'], $this->hosts);
    }

    public function testDeployIniInTheCurrentFolderWinsSilently(): void
    {
        $this->iniAt('deploy.ini', 'here.example.org');
        $this->iniAt('other/deploy.ini', 'other.example.org');

        $tester = $this->backup();

        self::assertStringNotContainsString('Using', $tester->getDisplay());
        self::assertSame(['here.example.org'], $this->hosts);
    }

    public function testSeveralInisFailWhenNotInteractive(): void
    {
        $this->iniAt('deploy.production.ini', 'p.example.org');
        $this->iniAt('deploy.staging.ini', 's.example.org');

        $this->expectExceptionMessage("deploy.production.ini\ndeploy.staging.ini");
        $this->backup();
    }

    public function testSeveralInisAreChosenWhenInteractive(): void
    {
        $this->iniAt('deploy.production.ini', 'p.example.org');
        $this->iniAt('deploy.staging.ini', 's.example.org');

        $this->backup([], ['1']);

        self::assertSame(['s.example.org'], $this->hosts);
    }

    public function testExplicitConfigSkipsTheDiscovery(): void
    {
        $this->iniAt('deploy.production.ini', 'p.example.org');
        $this->iniAt('deploy.staging.ini', 's.example.org');

        $this->backup(['--config' => 'deploy.staging.ini']);

        self::assertSame(['s.example.org'], $this->hosts);
    }

    public function testNoIniReportsTheMissingConfig(): void
    {
        $this->expectExceptionMessage('Config not found');
        $this->backup();
    }

    public function testExampleTemplatesAreNotCandidates(): void
    {
        file_put_contents("{$this->dir}/deploy.ini.example", "remote = \"ftps://u:p@x/y\"\n");

        $this->expectExceptionMessage('Config not found');
        $this->backup();
    }

    public function testAsksForTheSectionWhenInteractive(): void
    {
        $this->sectionedIni();

        $this->backup([], ['1']);

        self::assertSame(['example.org'], $this->hosts);
    }

    public function testSectionIsRequiredWhenNotInteractive(): void
    {
        $this->sectionedIni();

        $this->expectException(SectionRequired::class);
        $this->backup();
    }

    private function sectionedIni(): void
    {
        file_put_contents("{$this->dir}/deploy.ini", "pfdk_db = var/database.db\n[staging]\nremote = \"ftps://u:p@staging.example.org/app\"\n[production]\nremote = \"ftps://u:p@example.org/app\"\n");
    }

    private function iniAt(string $path, string $host): void
    {
        is_dir(dirname("{$this->dir}/$path")) || mkdir(dirname("{$this->dir}/$path"), 0777, true);
        file_put_contents("{$this->dir}/$path", "remote = \"ftps://u:p@$host/app\"\npfdk_db = var/database.db\n");
    }

    /**
     * @param array<string,mixed> $options
     * @param list<string>        $inputs
     */
    private function backup(array $options = [], array $inputs = []): CommandTester
    {
        $factory = function ($config) {
            $this->hosts[] = $config->host;

            return new InMemoryRemoteFiles();
        };
        $tester = new CommandTester(ApplicationFactory::create($factory)->find('backup'));
        $tester->setInputs($inputs);
        $tester->execute($options, ['interactive' => $inputs !== []]);

        return $tester;
    }
}
