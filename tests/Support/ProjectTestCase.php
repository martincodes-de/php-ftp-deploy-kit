<?php
declare(strict_types=1);

namespace Pfdk\Tests\Support;

use Pfdk\Config\DeployConfig;
use PHPUnit\Framework\TestCase;

abstract class ProjectTestCase extends TestCase
{
    protected string $dir;
    private string $originalDir;
    private string $originalPath;

    protected function setUp(): void
    {
        $this->originalDir = getcwd();
        $this->originalPath = (string) getenv('PATH');
        $this->dir = sys_get_temp_dir() . '/pfdk-' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0777, true);
        chdir($this->dir);
    }

    protected function tearDown(): void
    {
        chdir($this->originalDir);
        putenv('PATH=' . $this->originalPath);
        $this->removeDirectory($this->dir);
    }

    /** Puts a fake `ddev` first on PATH and marks the folder as a DDEV project; returns the log of its calls. */
    protected function installFakeDdev(string $status = 'running'): string
    {
        $bin = "{$this->dir}/fakebin";
        $log = "{$this->dir}/ddev.log";
        mkdir($bin);
        file_put_contents("$bin/ddev", <<<SH
            #!/bin/sh
            echo "ddev \$*" >> "$log"
            if [ "\$1" = "describe" ]; then echo '{"raw":{"status":"$status"}}'; exit 0; fi
            if [ "\$1" = "exec" ]; then shift; exec sh -c "\$*"; fi
            SH);
        chmod("$bin/ddev", 0755);
        putenv("PATH=$bin:{$this->originalPath}");
        is_dir("{$this->dir}/.ddev") || mkdir("{$this->dir}/.ddev");
        file_put_contents("{$this->dir}/.ddev/config.yaml", "name: test\n");

        return $log;
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
