## Why

Once real data lives in the live database (answers of guests, orders, ...), some deploys must not touch it at all, even though the ini has `pfdk_db`, a `pfdk backup` step and `pfdk_type = symfony`. Today the only way is to edit the ini or build a second ini for that run, and an interactive `deploy` still asks whether to pull, migrate and push the database.

## What Changes

- `pfdk deploy --no-db`: no migration offer, and the database steps that run inside the deploy are switched off. pfdk sets `PFDK_NO_DB=1` for dg, so `local:` jobs inherit it.
- `backup` does nothing and succeeds when `PFDK_NO_DB=1`, so a `before[]` backup step no longer downloads the database. `db:pull`, `db:push`, `db:init` and `db:migrate` refuse with an error, because they would change data.
- The variable also works on its own (`PFDK_NO_DB=1 pfdk db:push` refuses), for CI or a shell alias.

## Capabilities

### New Capabilities
- `deploy-no-db`: a deploy that never touches the database.

### Modified Capabilities
None.

## Impact

`DeployCommand`, `BaseCommand`, the five database commands, README.
