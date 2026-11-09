<?php
declare(strict_types=1);

namespace Pfdk\Remote;

interface RemoteFiles
{
    /**
     * Returns false when the file is missing or empty, so callers never keep a useless download.
     *
     * @throws \RuntimeException on connection, login or transfer errors, which must never look like "not found"
     */
    public function download(string $remotePath, string $localFile): bool;

    public function upload(string $localFile, string $remotePath): void;
}
