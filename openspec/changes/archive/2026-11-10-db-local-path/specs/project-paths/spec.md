## ADDED Requirements

### Requirement: Local database path can differ from the remote one
The optional ini key `pfdk_db_local` SHALL name the local database file, relative to the project directory. `db:pull`, `db:push` and `db:migrate` SHALL use it as the local file and `pfdk_db` as the remote file. Without `pfdk_db_local` both sides SHALL use `pfdk_db`.

#### Scenario: different paths
- **WHEN** `pfdk_db = var/data_prod.db`, `pfdk_db_local = var/data_dev.db` and `db:pull` runs
- **THEN** the live `var/data_prod.db` replaces the local `var/data_dev.db`, whose old content is saved as a safety copy

#### Scenario: push uses the local file
- **WHEN** the same ini is used with `db:push`
- **THEN** the local `var/data_dev.db` is uploaded to the remote `var/data_prod.db`

#### Scenario: key not set
- **WHEN** only `pfdk_db` is set
- **THEN** the same relative path is used locally and remotely
