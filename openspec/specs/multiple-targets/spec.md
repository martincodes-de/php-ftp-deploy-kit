# multiple-targets Specification

## Purpose
TBD - created by archiving change multiple-targets. Update Purpose after archive.

## Requirements

### Requirement: Sections select the target
If the ini has no top-level `remote`, its named sections SHALL be the deploy targets. `--section <name>` SHALL select one; its keys override top-level keys of the same name.

#### Scenario: section overrides
- **WHEN** the top level sets `pfdk_db = var/app.db` and section `production` sets `pfdk_backups = backups-prod`
- **THEN** with `--section production` the database is `var/app.db` and the backups folder is `backups-prod`

#### Scenario: single-target ini
- **WHEN** the ini has a top-level `remote`
- **THEN** it is used as before and `before[]`-style list keys are not mistaken for sections

### Requirement: Never guess a section
An ini with sections used without `--section` SHALL fail and list the available section names.

#### Scenario: missing section option
- **WHEN** `pfdk backup` runs against an ini with sections `staging` and `production` without `--section`
- **THEN** the command fails and the message names `staging` and `production`

#### Scenario: unknown section
- **WHEN** `--section live` is given and no such section exists
- **THEN** the command fails and lists the available sections

#### Scenario: section on a single-target ini
- **WHEN** `--section production` is given but the ini has a top-level `remote`
- **THEN** the command fails with a message that the ini has no sections

### Requirement: Deploy forwards the section
`pfdk deploy --section <name>` SHALL run dg/ftp-deployment for that section only.

#### Scenario: forwarded option
- **WHEN** `pfdk deploy --section production` runs
- **THEN** dg is started with `--section production`

### Requirement: Migrate keeps the section
`pfdk db:migrate` SHALL use the selected section for its pull and its push.

#### Scenario: migrate with section
- **WHEN** `pfdk db:migrate --section staging --yes` runs
- **THEN** the pull and the push address the remote of `staging`
