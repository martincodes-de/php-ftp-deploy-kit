<?php
declare(strict_types=1);

namespace Pfdk\Tests\Environment;

use Pfdk\Environment\Ddev;
use Pfdk\Tests\Support\ProjectTestCase;

final class DdevTest extends ProjectTestCase
{
    public function testDetectsARunningProject(): void
    {
        $this->installFakeDdev('running');

        self::assertTrue(Ddev::isRunning($this->dir));
    }

    public function testStoppedProjectIsNotRunning(): void
    {
        $this->installFakeDdev('stopped');

        self::assertFalse(Ddev::isRunning($this->dir));
    }

    public function testFolderWithoutDdevConfigNeverStartsDdev(): void
    {
        $log = $this->installFakeDdev('running');
        unlink("{$this->dir}/.ddev/config.yaml");

        self::assertFalse(Ddev::isRunning($this->dir));
        self::assertFileDoesNotExist($log);
    }

    public function testMissingDdevBinaryMeansNotRunning(): void
    {
        mkdir("{$this->dir}/.ddev");
        file_put_contents("{$this->dir}/.ddev/config.yaml", "name: test\n");
        putenv('PATH=' . $this->dir . '/empty');

        self::assertFalse(Ddev::isRunning($this->dir));
    }
}
