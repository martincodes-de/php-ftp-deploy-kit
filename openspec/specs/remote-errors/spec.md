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
A transfer that fails with a connection or timeout error (curl 7, 10, 28) SHALL be attempted again, up to `pfdk_retries` attempts in total (default 3, minimum 1), with a pause of 3 seconds and a message that does not contain credentials. Login errors and missing files SHALL NOT be retried. A download attempt SHALL start with an empty file.

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
`upload()` SHALL write to `<name>.pfdk-upload` next to the target, verify that the remote size equals the local size, and only then rename it onto the target. If the server refuses the rename, it SHALL delete the target and rename again. On any failure it SHALL remove the temporary file and leave the target unchanged, except when the rename fails after the target was deleted: the complete temporary file SHALL then be kept and named in the error.

#### Scenario: size does not match
- **WHEN** the remote size differs from the local size
- **THEN** no rename happens, the temporary file is deleted and the target is unchanged

#### Scenario: server refuses to rename over an existing file
- **WHEN** the first rename fails
- **THEN** the target is deleted and the rename is repeated
