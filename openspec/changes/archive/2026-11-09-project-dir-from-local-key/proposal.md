## Why

The first real run inside digital-birthday-invite showed two problems:

1. `pfdk_db` and `pfdk_backups` are resolved relative to the folder of the `deploy.ini`, but dg/ftp-deployment's `local` key says which folder is the project. With the recommended layout (`ftp-deployment/deploy.ini`, `local = ..`) `db:pull` wrote the database to `ftp-deployment/var/` instead of `var/`.
2. The `symfony-sqlite` preset runs `composer install --no-dev` before the backup step, which removes the dev-installed `pfdk` binary the backup step needs. The `|| true` hid the failure, so deploys would silently run without a backup.

## What Changes

- The project directory is the folder named by the ini's `local` key (relative to the ini, default: the ini's folder), not the ini's folder.
- The `symfony-sqlite` preset assumes the layout `ftp-deployment/deploy.ini` with `local = ..` and runs the backup before the production `composer install`.
- README documents installing pfdk in its own folder (`ftp-deployment/`) so `composer install --no-dev` cannot remove it, and where files end up.

## Capabilities

### New Capabilities
- `project-paths`: how pfdk resolves the project directory, database file and backup folder from `deploy.ini`.

### Modified Capabilities
None.

## Impact

`DeployConfig`, the `symfony-sqlite` preset, README. No change for ini files that keep `deploy.ini` in the project root with the default `local`.
