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
}
