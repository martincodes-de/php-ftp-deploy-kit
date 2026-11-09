<?php
declare(strict_types=1);

namespace Pfdk\Environment;

use Symfony\Component\Process\Process;

final class Ddev
{
    public static function isRunning(string $projectDir): bool
    {
        // Cheap check first: no DDEV project, no need to start a process.
        if (!is_file($projectDir . '/.ddev/config.yaml')) {
            return false;
        }

        try {
            $describe = new Process(['ddev', 'describe', '-j'], $projectDir);
            $describe->run();
        } catch (\Throwable) {
            return false;
        }

        return $describe->isSuccessful() && str_contains($describe->getOutput(), '"status":"running"');
    }
}
