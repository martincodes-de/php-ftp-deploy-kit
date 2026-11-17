# deploy-no-db

## Requirements

### Requirement: Deploy without touching the database
`pfdk deploy --no-db` SHALL NOT offer the migration before the deploy and SHALL run dg with the environment variable `PFDK_NO_DB=1`. It SHALL say once that the database is switched off.

#### Scenario: no migration question
- **WHEN** an interactive deploy of a Symfony project with `pfdk_db` and `pfdk_type = symfony` runs with `--no-db`
- **THEN** the question to pull, migrate and push the database is not asked and the database is unchanged

#### Scenario: the steps of the deploy see the switch
- **WHEN** a `before[]` job of the ini reads `PFDK_NO_DB`
- **THEN** it is `1` with `--no-db` and unset without it

### Requirement: Database commands respect the switch
When `PFDK_NO_DB=1` is set, `backup` SHALL do nothing and succeed, so a deploy with a backup step carries on without downloading the database. `db:pull`, `db:push`, `db:init` and `db:migrate` SHALL fail with an error and change nothing, locally and on the server.

#### Scenario: backup step is skipped
- **WHEN** `backup` runs with `PFDK_NO_DB=1` and a live database exists
- **THEN** nothing is downloaded, no backup file is written and the exit code is 0

#### Scenario: writing commands refuse
- **WHEN** `db:push -y` runs with `PFDK_NO_DB=1`
- **THEN** it fails with an error and the live database is unchanged
