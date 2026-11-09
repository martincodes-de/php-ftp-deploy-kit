# ddev-migrate Specification

## Purpose
TBD - created by archiving change ddev-migrate. Update Purpose after archive.

## Requirements

### Requirement: Detect a running DDEV project
pfdk SHALL consider DDEV active when the project folder contains `.ddev/config.yaml` and `ddev describe -j` run in that folder reports the project as running. The key `pfdk_ddev = off` SHALL disable the detection.

#### Scenario: DDEV running
- **WHEN** `.ddev/config.yaml` exists and `ddev describe -j` reports status `running`
- **THEN** DDEV is active

#### Scenario: DDEV stopped or missing
- **WHEN** the project is stopped, has no `.ddev/config.yaml`, or the `ddev` binary is not available
- **THEN** DDEV is not active

#### Scenario: disabled by config
- **WHEN** `pfdk_ddev = off`
- **THEN** DDEV is never used, even if it runs

### Requirement: Migrations use DDEV when it runs
`db:migrate` SHALL run its default migration command through `ddev exec` when DDEV is active, and on the host otherwise. `pfdk_migrate_command` SHALL replace the default in both cases.

#### Scenario: DDEV active
- **WHEN** DDEV is active and no `pfdk_migrate_command` is set
- **THEN** the migration runs as `ddev exec php bin/console doctrine:migrations:migrate --no-interaction`

#### Scenario: explicit command wins
- **WHEN** `pfdk_migrate_command` is set
- **THEN** exactly that command runs, without a DDEV prefix

### Requirement: Exec command
`pfdk exec <command>` SHALL run the command in the project folder, through `ddev exec` when DDEV is active and on the host otherwise, stream its output and return its exit status.

#### Scenario: failing command
- **WHEN** the command exits with a non-zero status
- **THEN** `pfdk exec` exits non-zero, so an ini job using it aborts the deploy

### Requirement: Presets build through pfdk exec
The `symfony-sqlite` preset SHALL run its local build steps through `pfdk exec`.

#### Scenario: preset content
- **WHEN** the preset is read
- **THEN** its `composer install` jobs are prefixed with `pfdk exec`
