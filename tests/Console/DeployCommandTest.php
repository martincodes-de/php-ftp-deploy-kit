<?php
declare(strict_types=1);

namespace Pfdk\Tests\Console;

use Pfdk\Console\ApplicationFactory;
use Pfdk\Tests\Support\ProjectTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class DeployCommandTest extends ProjectTestCase
{
    public function testRunsTheDeploymentToolWithTheIni(): void
    {
        $this->writeTool(0);

        $tester = $this->deploy([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('args: ' . $this->dir . '/deploy.ini', $tester->getDisplay());
        self::assertStringNotContainsString('--test', $tester->getDisplay());
    }

    public function testForwardsTheSectionToDg(): void
    {
        $tool = "{$this->dir}/fake-deployment";
        file_put_contents($tool, "<?php\necho 'args: ' . implode(' ', array_slice(\$argv, 1));\n");
        file_put_contents("{$this->dir}/deploy.ini", "pfdk_tool = $tool\n[staging]\nremote = \"ftps://u:p@s.example.org/app\"\n[production]\nremote = \"ftps://u:p@example.org/app\"\n");

        $tester = $this->deploy(['--section' => 'production']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('--section production', $tester->getDisplay());
    }

    public function testDryRunPassesTestFlag(): void
    {
        $this->writeTool(0);

        self::assertStringContainsString('--test', $this->deploy(['--dry' => true])->getDisplay());
    }

    public function testFailingToolFailsTheCommand(): void
    {
        $this->writeTool(3);

        self::assertSame(1, $this->deploy([])->getStatusCode());
    }

    public function testUsesTheToolInstalledInTheProject(): void
    {
        mkdir("{$this->dir}/vendor/dg/ftp-deployment", 0777, true);
        file_put_contents("{$this->dir}/vendor/dg/ftp-deployment/deployment", "<?php\necho 'project tool';\n");
        $this->writeIni();

        self::assertStringContainsString('project tool', $this->deploy([])->getDisplay());
    }

    public function testMissingToolIsReported(): void
    {
        $this->writeIni("pfdk_tool = {$this->dir}/missing\n");

        $tester = $this->deploy([]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('dg/ftp-deployment not found', $tester->getDisplay());
    }

    public function testFailedPullStopsBeforeDeploying(): void
    {
        $this->writeTool(0);

        $tester = $this->deploy(['--pull' => true]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringNotContainsString('args:', $tester->getDisplay());
    }

    /** @param array<string,mixed> $options */
    private function deploy(array $options): CommandTester
    {
        $tester = new CommandTester(ApplicationFactory::create()->find('deploy'));
        $tester->execute($options);

        return $tester;
    }

    private function writeTool(int $exitCode): void
    {
        $tool = "{$this->dir}/fake-deployment";
        file_put_contents($tool, "<?php\necho 'args: ' . implode(' ', array_slice(\$argv, 1));\nexit($exitCode);\n");
        $this->writeIni("pfdk_tool = $tool\n");
    }
}
