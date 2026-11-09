## ADDED Requirements

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
