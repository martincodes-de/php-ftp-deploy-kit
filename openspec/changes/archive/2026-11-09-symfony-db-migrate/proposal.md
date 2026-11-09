## Why

On FTP-only hosting there is no remote shell, so Doctrine migrations cannot run on the server. The workflow is: pull the live database, migrate it locally, push it back, then deploy the code. That is a standard Symfony case worth one command.

## What Changes

- New ini keys `pfdk_type = symfony` (explicit opt-in, never auto-detected) and optional `pfdk_migrate_command`.
- New command `pfdk db:migrate`: pulls the live database, runs the migration command in the project folder, then pushes the result. Any failing step stops the chain.

## Capabilities

### New Capabilities
- `symfony-migrate`: the pull-migrate-push workflow and its abort rules.

### Modified Capabilities
None.

## Impact

`DeployConfig`, a new `MigrateCommand`, `ApplicationFactory`, README. Projects without `pfdk_type = symfony` are unaffected.
