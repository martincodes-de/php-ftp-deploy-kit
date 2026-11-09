<?php
declare(strict_types=1);

namespace Pfdk\Tests\Console;

use Composer\InstalledVersions;
use Pfdk\Console\ApplicationFactory;
use PHPUnit\Framework\TestCase;

final class ConsoleTest extends TestCase
{
    public function testReportsTheVersionComposerKnows(): void
    {
        $package = 'martincodes-de/php-ftp-deploy-kit';
        $expected = InstalledVersions::isInstalled($package) ? InstalledVersions::getPrettyVersion($package) : 'dev';

        self::assertSame($expected, ApplicationFactory::create()->getVersion());
        self::assertNotSame('0.3.0', ApplicationFactory::create()->getVersion());
    }

    public function testRegistersAllCommands(): void
    {
        $names = array_keys(ApplicationFactory::create()->all());

        foreach (['init', 'deploy', 'backup', 'db:pull', 'db:push'] as $command) {
            self::assertContains($command, $names);
        }
    }
}
