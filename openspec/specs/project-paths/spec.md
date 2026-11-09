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
