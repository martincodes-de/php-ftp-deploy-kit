# db-init

## Requirements

### Requirement: db:init prepares a fresh server
`db:init` SHALL create the folder of `pfdk_db` on the remote (including missing parents), check that no live database exists, and upload the local database. It SHALL require `pfdk_db` and the local database file.

#### Scenario: fresh server
- **WHEN** the remote has no folder for the database and `db:init` runs
- **THEN** the folder is created and the local database is uploaded

#### Scenario: live database exists
- **WHEN** a live database already exists
- **THEN** `db:init` exits with an error that points to `db:push`, and the live database is untouched

#### Scenario: local database missing
- **WHEN** the local database file does not exist
- **THEN** `db:init` exits with an error and changes nothing on the remote
