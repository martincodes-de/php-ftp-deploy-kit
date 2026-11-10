## Why

`upload()` wrote straight onto the live file. A connection that broke midway left a truncated database on the server.

## What Changes

- Upload to `<name>.pfdk-upload`, check the remote size, then rename (RNFR/RNTO). If the server refuses to rename over an existing file, delete the target and rename again, like dg's `renameFile`. Any failure removes the temporary file; only a failed rename after the delete keeps it, because it then holds the only complete copy.
- `file://` targets use PHP `copy` and `rename`.

## Capabilities

### New Capabilities
None.

### Modified Capabilities
- `remote-errors`

## Impact

`CurlRemoteFiles::upload()`, README.
