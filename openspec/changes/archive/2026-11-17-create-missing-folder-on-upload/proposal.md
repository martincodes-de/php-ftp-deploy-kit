## Why

Over `ftp://` and `ftps://` the first `db:push` creates a missing folder for the database file (curl's `CURLOPT_FTP_CREATE_MISSING_DIRS`). Over `file://`, the scheme meant for rehearsing a deploy, the same push fails with "cannot write var/database/database.db" because the folder `var/` does not exist yet. A rehearsal therefore behaves differently from the real deploy.

## What Changes

- Uploads to a `file://` remote create a missing target folder (recursively) before writing, like the FTP transfer does.
- A folder that cannot be created is reported as an upload failure and nothing is written.

## Capabilities

### New Capabilities
None.

### Modified Capabilities
- `remote-errors`: uploads create missing folders on every scheme.

## Impact

`CurlRemoteFiles::uploadToFolder()`, its test, README.
