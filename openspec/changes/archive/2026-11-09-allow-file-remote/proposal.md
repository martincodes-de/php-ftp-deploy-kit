## Why

dg/ftp-deployment accepts `remote = "file:///some/folder"` for deploying into a local folder. pfdk rejected it because it requires a host. That makes it impossible to rehearse a full deploy, including the database commands, against a local folder before touching a real server.

## What Changes

- A `file://` remote without a host is valid.

## Capabilities

### New Capabilities
- `remote-config`: which `remote` values pfdk accepts.

### Modified Capabilities
None.

## Impact

`DeployConfig` only. FTP, FTPS and SFTP remotes keep requiring a host.
