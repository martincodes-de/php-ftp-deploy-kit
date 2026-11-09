## Why

`download()` returns false both when the remote file does not exist and when the connection or login fails. So `pfdk backup` reports "nothing backed up" with exit code 0 on a wrong password, and a deploy would continue without a backup.

## What Changes

- A missing remote file stays a normal "not found" result.
- Any other transfer error (connection, login, TLS, ...) throws, so the command exits non-zero and an ini job such as the backup step aborts the deploy.

## Capabilities

### New Capabilities
- `remote-errors`: how missing files and transfer errors are told apart.

### Modified Capabilities
None.

## Impact

`CurlRemoteFiles`, the `RemoteFiles` contract and the test fake. No config change.
