<?php
declare(strict_types=1);

namespace Pfdk\Tests\Support;

use Pfdk\Remote\RemoteFiles;

final class InMemoryRemoteFiles implements RemoteFiles
{
    public bool $unreachable = false;

    /** @param array<string,string> $files */
    public function __construct(public array $files = [])
    {
    }

    public function download(string $remotePath, string $localFile): bool
    {
        if ($this->unreachable) {
            throw new \RuntimeException('Download failed: connection refused');
        }
        if (($this->files[$remotePath] ?? '') === '') {
            return false;
        }
        is_dir(dirname($localFile)) || mkdir(dirname($localFile), 0777, true);
        file_put_contents($localFile, $this->files[$remotePath]);

        return true;
    }

    public function upload(string $localFile, string $remotePath): void
    {
        $this->files[$remotePath] = (string) file_get_contents($localFile);
    }

    /** @var array<string,true> */
    public array $folders = [];

    public function createFolder(string $remoteFolder): void
    {
        $this->assertReachable();
        $path = '';
        foreach (explode('/', trim($remoteFolder, '/')) as $part) {
            $path = ltrim("$path/$part", '/');
            $this->folders[$path] = true;
        }
    }

    public function names(string $remoteFolder): array
    {
        $this->assertReachable();
        $folder = trim($remoteFolder, '/');
        $prefix = $folder === '' ? '' : "$folder/";
        if ($folder !== '' && !isset($this->folders[$folder]) && !$this->hasChildren($folder)) {
            throw new \RuntimeException('Listing failed: no such folder');
        }

        $names = [];
        foreach (array_merge(array_keys($this->files), array_keys($this->folders)) as $path) {
            if ($prefix === '' || str_starts_with($path, $prefix)) {
                $names[] = explode('/', substr($path, strlen($prefix)))[0];
            }
        }

        return array_values(array_unique(array_filter($names, static fn (string $name): bool => $name !== '')));
    }

    public function deleteFile(string $remotePath): bool
    {
        $this->assertReachable();
        if (!isset($this->files[$remotePath])) {
            return false;
        }
        // a real server keeps the folders when their last file goes
        dirname($remotePath) === '.' || $this->createFolder(dirname($remotePath));
        unset($this->files[$remotePath]);

        return true;
    }

    public function deleteFolder(string $remotePath): bool
    {
        $this->assertReachable();
        if ((!isset($this->folders[$remotePath]) && !$this->hasChildren($remotePath)) || $this->hasChildren($remotePath)) {
            return false;
        }
        unset($this->folders[$remotePath]);

        return true;
    }

    private function hasChildren(string $folder): bool
    {
        foreach (array_merge(array_keys($this->files), array_keys($this->folders)) as $path) {
            if (str_starts_with($path, "$folder/")) {
                return true;
            }
        }

        return false;
    }

    private function assertReachable(): void
    {
        if ($this->unreachable) {
            throw new \RuntimeException('connection refused');
        }
    }
}
