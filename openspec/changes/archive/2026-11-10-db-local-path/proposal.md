## Why

`pfdk_db` is used for the local and the remote path. Symfony setups with one database file per `APP_ENV` (`var/data_dev.db` locally, `var/data_prod.db` live) could not use `db:pull`, `db:push` and `db:migrate` correctly.

## What Changes

- New optional key `pfdk_db_local`: the local database file for pull, push and migrate. Without it nothing changes.

## Capabilities

### New Capabilities
None.

### Modified Capabilities
- `project-paths`: the local database path can differ from the remote one.

## Impact

`DeployConfig`, README configuration table.
