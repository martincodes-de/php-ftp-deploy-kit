<?php
declare(strict_types=1);

namespace Pfdk\Console;

use Pfdk\Config\DeployConfig;
use Pfdk\Remote\CurlRemoteFiles;
use Pfdk\Remote\RemoteFiles;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

abstract class BaseCommand extends Command
{
    /** @var (callable(DeployConfig):RemoteFiles)|null swapped for an in-memory fake in tests */
    private $remoteFactory;

    /** @param (callable(DeployConfig):RemoteFiles)|null $remoteFactory */
    public function __construct(?callable $remoteFactory = null)
    {
        parent::__construct();
        $this->remoteFactory = $remoteFactory;
    }

    protected function configure(): void
    {
        $this->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to deploy.ini', 'deploy.ini');
    }

    protected function config(InputInterface $input): DeployConfig
    {
        $path = (string) $input->getOption('config');

        return DeployConfig::load(preg_match('~^(/|[A-Za-z]:)~', $path) === 1 ? $path : getcwd() . '/' . $path);
    }

    protected function remote(DeployConfig $config): RemoteFiles
    {
        return $this->remoteFactory !== null ? ($this->remoteFactory)($config) : new CurlRemoteFiles($config);
    }

    protected function stamp(): string
    {
        return date('Ymd-His');
    }
}
