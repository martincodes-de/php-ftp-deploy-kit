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

    /** Creates the folder and missing parents; succeeds when it exists. */
    public function createFolder(string $remoteFolder): void;

    /**
     * @return list<string> names in the folder without . and ..
     *
     * @throws \RuntimeException when the folder cannot be listed
     */
    public function names(string $remoteFolder): array;

    /** Returns false when the server refuses (missing file, or a folder). */
    public function deleteFile(string $remotePath): bool;

    /** Returns false when the server refuses (missing folder, not empty, or a file). */
    public function deleteFolder(string $remotePath): bool;
}
