<?php
declare(strict_types=1);

namespace Pfdk\Tests\Environment;

use Pfdk\Environment\Requirements;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RequirementsTest extends TestCase
{
    public function testEverythingPresentMeansNoProblems(): void
    {
        $all = new Requirements(['ftp', 'sftp', 'file'], true, ['openssl', 'ssh2']);

        foreach (['ftp', 'ftps', 'sftp', 'file'] as $scheme) {
            self::assertSame([], $all->problems($scheme, forDeploy: true), $scheme);
        }
    }

    #[DataProvider('brokenSetups')]
    public function testReportsWhatIsMissing(Requirements $requirements, string $scheme, bool $forDeploy, string $expected): void
    {
        $problems = $requirements->problems($scheme, $forDeploy);

        self::assertCount(1, $problems);
        self::assertStringContainsString($expected, $problems[0]);
    }

    /** @return iterable<string,array{Requirements,string,bool,string}> */
    public static function brokenSetups(): iterable
    {
        yield 'sftp without libssh2 in curl' => [new Requirements(['ftp'], true, ['ssh2']), 'sftp', false, 'libssh2'];
        yield 'sftp without ssh2 extension for deploy' => [new Requirements(['sftp'], true, []), 'sftp', true, 'ssh2'];
        yield 'ftps without curl ssl' => [new Requirements(['ftp'], false, ['openssl']), 'ftps', false, 'SSL'];
        yield 'ftps without openssl for deploy' => [new Requirements(['ftp'], true, []), 'ftps', true, 'openssl'];
        yield 'ftp without curl ftp' => [new Requirements(['file'], true, []), 'ftp', false, 'FTP'];
        yield 'file without curl file' => [new Requirements(['ftp'], true, []), 'file', false, 'local folder'];
        yield 'unknown scheme' => [new Requirements(['ftp'], true, []), 'gopher', false, "Unsupported scheme 'gopher://'"];
    }

    public function testDeployOnlyNeedsExtensionsWhenDeploying(): void
    {
        $withoutSsh2 = new Requirements(['sftp'], true, []);

        self::assertSame([], $withoutSsh2->problems('sftp', forDeploy: false));
    }

    public function testDescribesTheRuntime(): void
    {
        $description = (new Requirements(['ftp', 'sftp'], true, ['ssh2']))->describe();

        self::assertSame('ftp sftp', $description['curl protocols']);
        self::assertSame(['yes', 'no'], [$description['ext-ssh2'], $description['ext-openssl']]);
    }

    public function testRuntimeFactoryReflectsThisPhp(): void
    {
        self::assertContains('ftp', explode(' ', Requirements::fromRuntime()->describe()['curl protocols']));
    }
}
