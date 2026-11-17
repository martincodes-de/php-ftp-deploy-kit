# remote-transfer

## Requirements

### Requirement: Upload a file
`remote:upload <local> <remote>` SHALL upload the local file to the path below the remote path, using the same atomic upload as `db:push`, and create missing folders. It SHALL fail when the local file does not exist. It SHALL ask for confirmation naming host and path unless `--yes` is given, because an existing remote file is replaced.

#### Scenario: upload into a new folder
- **WHEN** `remote:upload notes.txt docs/notes.txt --yes` runs and `docs` does not exist
- **THEN** the folder is created and the file arrives with the same content

#### Scenario: local file missing
- **WHEN** the local file does not exist
- **THEN** the command fails and nothing is uploaded

### Requirement: Download a file
`remote:download <remote> <local>` SHALL download the file to the local path and create missing local folders. It SHALL fail with a clear message when the remote file is missing or empty and leave no local file. An existing local file SHALL only be replaced after confirmation or with `--yes`.

#### Scenario: download a file
- **WHEN** the remote holds `docs/notes.txt`
- **THEN** the local file has the same content

#### Scenario: remote file missing
- **WHEN** the remote file does not exist
- **THEN** the command fails and no local file is created or changed

#### Scenario: local file exists
- **WHEN** the local file exists and the question is answered with no
- **THEN** the local file is unchanged
