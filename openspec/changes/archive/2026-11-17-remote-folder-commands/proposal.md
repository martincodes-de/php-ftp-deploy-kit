## Why

A first deploy to a fresh server needs a few steps that pfdk could not do: the folder of the database file does not exist yet, so `backup` and `db:push` abort (the server answers "denied you to change to the given directory", which pfdk correctly treats as an access error), and nothing lists, creates or removes remote folders. Users end up writing throw-away curl or FTP scripts for a standard situation.

## What Changes

- New `db:init`: prepares a fresh server. It creates the folder of the database file and uploads the local database for the first time. It refuses when a live database already exists, so it can never overwrite one.
- New `remote:mkdir`, `remote:ls` and `remote:rm [-r]`: create, list and remove folders and files below the remote path. `remote:rm` needs an explicit path, asks before it deletes and never accepts the remote root.
- New `remote:upload <local> <remote>` and `remote:download <remote> <local>`: move any file between the project and the server with the same safe transfer as the database commands (atomic upload with size check).
- `RemoteFiles` gets `createFolder()`, `names()`, `deleteFile()` and `deleteFolder()`.

## Capabilities

### New Capabilities
- `db-init`: first push to a fresh server.
- `remote-folders`: list, create and remove remote folders and files.
- `remote-transfer`: upload and download single files.

### Modified Capabilities
None.

## Impact

`RemoteFiles`, `CurlRemoteFiles`, six new commands, `ApplicationFactory`, tests, README.
