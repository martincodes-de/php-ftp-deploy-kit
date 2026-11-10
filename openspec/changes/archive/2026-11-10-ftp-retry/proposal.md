## Why

On a real host roughly every first connection timed out after 20 s and the second one worked.

## What Changes

- `CurlRemoteFiles` retries connection and timeout errors (curl 7, 10, 28, plus 55 and 56 for downloads and the temporary upload), 3 attempts by default, 3 s apart, with a message. Login and not-found errors are never retried. Each download attempt starts a fresh file.
- Optional key `pfdk_retries` (total attempts, 1 to 10, default 3, `1` = off; other values are a configuration error).

## Capabilities

### New Capabilities
None.

### Modified Capabilities
- `remote-errors`

## Impact

`CurlRemoteFiles`, `DeployConfig`, `BaseCommand` (passes the retry message to the console), README.
