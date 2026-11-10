## Why

`upload()` wrote straight onto the live file. A connection that broke midway left a truncated database on the server.

## What Changes

- Upload to `<name>.pfdk-upload`, check the remote size (abort if the server reports none), then rename (RNFR/RNTO). Only the transfer is repeated on connection errors; size checks, rename and deletes run once. A lost answer to the rename is settled by reading the state back (SIZE of target and temporary file), never by repeating or deleting. Deleting the target and renaming again is only the fallback when the server explicitly refuses to rename over an existing file (like dg's `renameFile`), after the temporary file was confirmed complete. If the rename fails after that delete, the complete temporary file stays and is named.
- `file://` targets use PHP `copy` and `rename` on the configured path, and remove a partial file if the copy fails.

## Capabilities

### New Capabilities
None.

### Modified Capabilities
- `remote-errors`

## Impact

`CurlRemoteFiles::upload()`, README.
