## ADDED Requirements

### Requirement: Opt-in Symfony type
`db:migrate` SHALL only run when the ini contains `pfdk_type = symfony`.

#### Scenario: type not set
- **WHEN** `pfdk db:migrate` runs without `pfdk_type = symfony`
- **THEN** it fails with a message naming the key and changes nothing

### Requirement: Pull, migrate, push
`db:migrate` SHALL pull the live database, run the migration command in the project folder, and push the migrated database back, in that order. The default migration command is `php bin/console doctrine:migrations:migrate --no-interaction`; `pfdk_migrate_command` SHALL replace it.

#### Scenario: successful migration
- **WHEN** all three steps succeed
- **THEN** the live database equals the migrated local database and the previous live database is in the backup folder

#### Scenario: custom command
- **WHEN** `pfdk_migrate_command = ddev exec php bin/console doctrine:migrations:migrate --no-interaction`
- **THEN** that command runs instead of the default

### Requirement: Any failing step aborts
If the pull fails, the migration SHALL NOT run. If the migration fails, nothing SHALL be pushed.

#### Scenario: pull fails
- **WHEN** the pull fails
- **THEN** the migration command is not executed and the command exits non-zero

#### Scenario: migration fails
- **WHEN** the migration command exits non-zero
- **THEN** the live database is untouched, the command exits non-zero and the message says where the pulled copy is

### Requirement: Confirmation before overwriting
`db:migrate` SHALL ask before pushing unless `--yes` is given.

#### Scenario: declined
- **WHEN** the user answers no at the push prompt
- **THEN** the live database is untouched
