<?php
declare(strict_types=1);

namespace Pfdk\Remote;

interface RemoteFiles
{
    /** Returns false when the file is missing or empty, so callers never keep a useless download. */
    public function download(string $remotePath, string $localFile): bool;

    public function upload(string $localFile, string $remotePath): void;
}
