<?php
declare(strict_types=1);

namespace Pfdk\Tests\Console;

use Pfdk\Console\ApplicationFactory;
use PHPUnit\Framework\TestCase;

final class ConsoleTest extends TestCase
{
    public function testRegistersAllCommands(): void
    {
        $names = array_keys(ApplicationFactory::create()->all());

        foreach (['init', 'deploy', 'backup', 'db:pull', 'db:push'] as $command) {
            self::assertContains($command, $names);
        }
    }
}
