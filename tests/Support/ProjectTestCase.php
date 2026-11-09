<?php
declare(strict_types=1);

namespace Pfdk\Tests\Support;

use Pfdk\Config\DeployConfig;
use PHPUnit\Framework\TestCase;

abstract class ProjectTestCase extends TestCase
{
    protected string $dir;
    private string $originalDir;

    protected function setUp(): void
    {
        $this->originalDir = getcwd();
        $this->dir = sys_get_temp_dir() . '/pfdk-' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0777, true);
        chdir($this->dir);
    }

    protected function tearDown(): void
    {
        chdir($this->originalDir);
        $this->removeDirectory($this->dir);
    }

    protected function writeIni(string $extra = "pfdk_db = var/database.db\n"): string
    {
        $file = "{$this->dir}/deploy.ini";
        file_put_contents($file, "remote = \"ftp://user:secret@example.org/web/app\"\n$extra");

        return $file;
    }

    protected function config(string $extra = "pfdk_db = var/database.db\n"): DeployConfig
    {
        return DeployConfig::load($this->writeIni($extra));
    }

    protected function writeLocalDb(string $contents): string
    {
        $file = "{$this->dir}/var/database.db";
        is_dir(dirname($file)) || mkdir(dirname($file), 0777, true);
        file_put_contents($file, $contents);

        return $file;
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
