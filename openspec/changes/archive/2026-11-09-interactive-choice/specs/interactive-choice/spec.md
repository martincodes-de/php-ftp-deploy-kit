## ADDED Requirements

### Requirement: Find the ini without -c
Without `--config`, pfdk SHALL use `deploy.ini` in the current folder if it exists. Otherwise it SHALL look for ini files matching `deploy*.ini` (not `.example`) in the current folder and one level below, use the only one found and print which one, or fail with a list of candidates if there are several and the terminal is not interactive, or ask which one to use if it is.

#### Scenario: one candidate in a subfolder
- **WHEN** only `ftp-deployment/deploy.ini` exists and `pfdk backup` runs from the project root without `-c`
- **THEN** that file is used and its path is printed

#### Scenario: several candidates, interactive
- **WHEN** `deploy.staging.ini` and `deploy.production.ini` exist and the terminal is interactive
- **THEN** pfdk asks which one to use

#### Scenario: several candidates, non-interactive
- **WHEN** several candidates exist and the run is non-interactive
- **THEN** the command fails and lists them

#### Scenario: explicit config wins
- **WHEN** `--config` is given
- **THEN** no discovery happens

### Requirement: Ask for the section
If the ini has sections and `--section` is missing, pfdk SHALL ask which section to use in an interactive terminal, and SHALL fail listing the sections otherwise.

#### Scenario: interactive section choice
- **WHEN** an ini has sections `staging` and `production` and the user picks `production`
- **THEN** the command runs for `production`

### Requirement: Offer the migration before a Symfony deploy
In an interactive terminal, `pfdk deploy` SHALL ask whether to pull, migrate and push the database first when `pfdk_type = symfony` and `pfdk_db` are set. The default SHALL be no. If the user says yes and the migration fails, the deploy SHALL NOT run.

#### Scenario: user accepts and migration fails
- **WHEN** the user answers yes and `db:migrate` fails
- **THEN** `deploy` exits non-zero without starting dg

#### Scenario: non-interactive
- **WHEN** `pfdk deploy` runs without interaction
- **THEN** no question is asked and the database is not touched
