# remote-folders

## Requirements

### Requirement: Create and list remote folders
`remote:mkdir <path>` SHALL create the folder and missing parents below the remote path and succeed when it already exists. `remote:ls [path]` SHALL print the names in a folder, one per line, without `.` and `..`; without a path it lists the remote path itself.

#### Scenario: create nested folders
- **WHEN** `remote:mkdir var/database` runs on a fresh remote
- **THEN** `var` and `var/database` exist afterwards

#### Scenario: list a folder
- **WHEN** `remote:ls var` runs and the folder holds `database` and `cache`
- **THEN** both names are printed

### Requirement: Remove remote files and folders safely
`remote:rm <path>` SHALL require a path that is not empty, `/`, `.` or contains `..`. Without `-r` it SHALL delete one file or one empty folder. With `-r` it SHALL delete the folder and everything in it, files first and folders last. It SHALL ask for confirmation naming the host and the path unless `--yes` is given. A path that does not exist SHALL be an error.

#### Scenario: delete a folder with content
- **WHEN** `remote:rm -r var --yes` runs and `var` holds a folder with a file
- **THEN** the file, the inner folder and `var` are gone

#### Scenario: refuse the root
- **WHEN** `remote:rm -r /` or `remote:rm ""` runs
- **THEN** it exits with an error and deletes nothing

#### Scenario: not confirmed
- **WHEN** the question is answered with no
- **THEN** nothing is deleted
