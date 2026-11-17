## 1. Remote operations

- [x] 1.1 `RemoteFiles`: `createFolder()`, `names()`, `deleteFile()`, `deleteFolder()`; implement them in `CurlRemoteFiles` (file:// and curl) and in `InMemoryRemoteFiles`
- [x] 1.2 Tests for the new operations against `file://localhost/...`

## 2. Commands

- [x] 2.1 `remote:mkdir`, `remote:ls`, `remote:rm` with tests (happy path, refused root, not confirmed, missing path, unreachable server)
- [x] 2.2 `remote:upload` and `remote:download` with tests (new folder, missing local file, missing remote file, existing local file, not confirmed)
- [x] 2.3 `db:init` with tests (fresh server, live database exists, local database missing)
- [x] 2.4 Register the commands, document them in the README (commands table, first deploy)
- [x] 2.5 Remove unnecessary comments from the touched code

## 3. Wrap-up

- [x] 3.1 Run the full test suite and make it pass
