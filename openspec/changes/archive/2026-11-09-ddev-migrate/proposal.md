## Why

Many projects run PHP only inside DDEV, so `php bin/console …` on the host fails or uses the wrong PHP version. Users should not have to wire `ddev exec` into every command by hand; pfdk should notice that DDEV is running and use it.

## What Changes

- pfdk detects a running DDEV project for the project folder (`.ddev/config.yaml` present and `ddev describe -j` reports running). `pfdk_ddev = off` disables the detection.
- `db:migrate` runs its default migration command through `ddev exec` when DDEV runs, and on the host otherwise. An explicit `pfdk_migrate_command` always wins.
- New command `pfdk exec <command>` runs a shell command in the project folder, through DDEV when it runs, so the preset's build steps (`composer install --no-dev`, …) work with and without DDEV.
- The presets use `pfdk exec` for their local build steps.

## Capabilities

### New Capabilities
- `ddev-migrate`: DDEV detection and the commands that use it.

### Modified Capabilities
None.

## Impact

New `Ddev` helper, `exec` command, `MigrateCommand`, `DeployConfig` (`pfdk_ddev`), presets, README.
