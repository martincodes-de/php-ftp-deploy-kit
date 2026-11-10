# project-paths Specification

## Purpose
TBD - created by archiving change project-dir-from-local-key. Update Purpose after archive.

## Requirements

### Requirement: Project directory follows the local key
pfdk SHALL treat the folder named by the ini's `local` key, resolved relative to the folder containing the ini, as the project directory. Without a `local` key the project directory SHALL be the folder containing the ini.

#### Scenario: ini in a subfolder pointing at the project root
- **WHEN** `ftp-deployment/deploy.ini` contains `local = ..`
- **THEN** the project directory is the parent of `ftp-deployment/`

#### Scenario: no local key
- **WHEN** the ini has no `local` key
- **THEN** the project directory is the folder containing the ini

#### Scenario: absolute local path
- **WHEN** `local` is an absolute path
- **THEN** that path is the project directory

### Requirement: Database and backups live under the project directory
The local database file (`pfdk_db`) and a relative backup folder (`pfdk_backups`) SHALL be resolved against the project directory.

#### Scenario: pull writes into the project
- **WHEN** `pfdk db:pull` runs with `local = ..` and `pfdk_db = var/app.db`
- **THEN** the database is written to `<project>/var/app.db`

#### Scenario: relative backup folder
- **WHEN** `pfdk_backups = ftp-deployment/backups` and `local = ..`
- **THEN** backups are written to `<project>/ftp-deployment/backups/`

### Requirement: Preset backs up before the production build
The `symfony-sqlite` preset SHALL run the database backup before any step that removes dev dependencies.

#### Scenario: step order
- **WHEN** the preset's `before[]` jobs are read in order
- **THEN** the backup job comes before `composer install --no-dev`

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

#### Scenario: empty value
- **WHEN** `pfdk_db_local` is empty or only `/`
- **THEN** it counts as not set

#### Scenario: migration did not change the local file
- **WHEN** `db:migrate` finishes and the local file is unchanged
- **THEN** nothing is pushed and a warning points at `DATABASE_URL`
