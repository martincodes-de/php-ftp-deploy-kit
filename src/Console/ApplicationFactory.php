<?php
declare(strict_types=1);

namespace Pfdk\Console;

use Pfdk\Config\DeployConfig;
use Pfdk\Remote\RemoteFiles;
use Symfony\Component\Console\Application;

final class ApplicationFactory
{
    /** @param (callable(DeployConfig):RemoteFiles)|null $remoteFactory */
    public static function create(?callable $remoteFactory = null): Application
    {
        $application = new Application('pfdk (PHP FTP Deploy Kit)', '0.3.0');
        $application->addCommands([
            new InitCommand($remoteFactory),
            new DeployCommand($remoteFactory),
            new BackupCommand($remoteFactory),
            new DbPullCommand($remoteFactory),
            new DbPushCommand($remoteFactory),
            new MigrateCommand($remoteFactory),
        ]);

        return $application;
    }
}
