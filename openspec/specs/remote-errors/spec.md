# remote-errors Specification

## Purpose
TBD - created by archiving change fail-on-remote-errors. Update Purpose after archive.

## Requirements

### Requirement: Missing file is not an error
Downloading a file that does not exist remotely, or exists but is empty, SHALL report "not found" without failing the command.

#### Scenario: no live database yet
- **WHEN** `pfdk backup` runs and the remote database file does not exist
- **THEN** it prints that nothing was backed up and exits with status 0

### Requirement: Transfer errors abort
Any other download failure (connection refused, login rejected, TLS failure) SHALL raise an error with curl's message, so the command exits with a non-zero status.

#### Scenario: server unreachable during backup
- **WHEN** `pfdk backup` runs and the server cannot be reached
- **THEN** the command fails with a non-zero exit status and does not report "nothing backed up"

#### Scenario: deploy aborts on failed backup
- **WHEN** the preset's backup job fails
- **THEN** dg/ftp-deployment stops before uploading anything

### Requirement: Failed pull never touches the local database
If `db:pull` fails for any reason, the local database SHALL stay unchanged.

#### Scenario: unreachable server during pull
- **WHEN** `db:pull` runs and the server cannot be reached
- **THEN** the command fails and the local database file is unchanged

### Requirement: Retry connection failures
A transfer that fails with a connection or timeout error (curl 7, 10, 28, and 55 or 56 in the middle of a download or of the upload of the temporary file; those two only there, because a repeated send or receive is harmless, while a repeated rename or delete is not) SHALL be attempted again, up to `pfdk_retries` attempts in total (1 to 10, default 3; anything else is a configuration error), with a pause of 3 seconds and a message that does not contain credentials. Login errors and missing files SHALL NOT be retried. A download attempt SHALL start with an empty file.

#### Scenario: timeout, then success
- **WHEN** the first attempt times out and the second succeeds
- **THEN** the command succeeds and one retry message was shown

#### Scenario: all attempts fail
- **WHEN** every attempt times out
- **THEN** the command fails with "failed after N attempts" and the last error

#### Scenario: login denied
- **WHEN** the server rejects the login
- **THEN** there is no second attempt

### Requirement: Uploads replace the live file atomically
`upload()` SHALL write to `<name>.pfdk-upload` next to the target, verify that the remote size equals the local size, and only then rename it onto the target. Only the transfer of the temporary file MAY be repeated on connection errors; size checks, the rename and every delete SHALL run once. If the server reports no size (SIZE unsupported), the upload SHALL be aborted instead of renamed unchecked.

After a connection error during the rename, pfdk SHALL read the state back: the target has the local size and the temporary file is gone means success; the temporary file is still there means the rename did not happen, so the temporary file is removed and the target is untouched; if the state cannot be read, pfdk SHALL abort with a message naming target and temporary file and SHALL delete nothing.

The target SHALL only be deleted as a fallback when the server explicitly refuses the rename (curl 21) and the temporary file was confirmed complete. If the rename fails after that delete, the complete temporary file SHALL be kept and named in the error, but only if it was confirmed to exist. In all other failures the temporary file SHALL be removed (one attempt) and the target stays unchanged.

#### Scenario: size does not match
- **WHEN** the remote size differs from the local size
- **THEN** no rename happens, the temporary file is deleted and the target is unchanged

#### Scenario: server reports no size
- **WHEN** SIZE returns no value
- **THEN** the upload is aborted with "Server reports no file size", nothing is renamed

#### Scenario: rename answer lost after it was carried out
- **WHEN** the rename succeeds on the server but the answer times out
- **THEN** the state check finds the new file at the target, the upload counts as done and nothing is deleted

#### Scenario: connection error never deletes the target
- **WHEN** the rename fails with a connection error
- **THEN** the target is never deleted

#### Scenario: server refuses to rename over an existing file
- **WHEN** the rename is refused with a server error and the temporary file is confirmed complete
- **THEN** the target is deleted and the rename is repeated once

#### Scenario: file target with special characters
- **WHEN** a `file://` target name contains `#`, `?` or `%`
- **THEN** the file is written under exactly that name
