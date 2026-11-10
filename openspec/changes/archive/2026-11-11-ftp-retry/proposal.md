## Why

On a real host roughly every first connection timed out after 20 s and the second one worked.

## What Changes

- `CurlRemoteFiles` retries connection and timeout errors (curl 7, 10, 28), 3 attempts by default, 3 s apart, with a message. Login and not-found errors are never retried. Each download attempt starts a fresh file.
- Optional key `pfdk_retries` (total attempts, default 3, `1` = off).

## Capabilities

### New Capabilities
None.

### Modified Capabilities
- `remote-errors`

## Impact

`CurlRemoteFiles`, `DeployConfig`, README.
