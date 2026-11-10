# PHP FTP Deploy Kit (`pfdk`)

Deploy a PHP app to FTP-only hosting in a couple of minutes. A thin layer over [dg/ftp-deployment](https://github.com/dg/ftp-deployment): presets, safe SQLite pull/push and a few helpers. dg does the deploying.

- **For:** classic shared hosts (bplaced and similar), no SSH, no Composer on the server. You build locally and upload the result.
- **Not for:** hosts with SSH. A tool that runs commands on the server (for example Deployer) fits better.
- **Presets:** Symfony (with or without SQLite) and static. More are welcome, see [CONTRIBUTING.md](CONTRIBUTING.md).

## Quick start

Needs PHP 8.2+ with `ext-curl`. Install pfdk in its own folder, so `composer install --no-dev` during a deploy cannot remove it.

```bash
mkdir ftp-deployment && cd ftp-deployment
composer require martincodes-de/php-ftp-deploy-kit
php vendor/bin/pfdk init symfony          # or: symfony-sqlite, static
```

`init` creates `deploy.ini` and ignores it in git. Put your connection into it (`remote = "ftps://USER:PASSWORD@HOST/PATH"`), then run from the project root:

```bash
php ftp-deployment/vendor/bin/pfdk deploy --dry     # preview
php ftp-deployment/vendor/bin/pfdk deploy
```

| Scheme | Meaning |
| --- | --- |
| `ftps://` | FTP over TLS (recommended) |
| `ftp://` | Plain FTP, password unencrypted; pfdk warns |
| `sftp://` | Needs the `ssh2` PHP extension and curl built with libssh2 |
| `file:///path` | A local folder, to rehearse a deploy |

## First deploy (Symfony)

1. Create `.env.prod.local` in the project root with the production secrets (`APP_SECRET`, API keys). Git ignores it, the deploy uploads it.
2. With SQLite: the deploy never uploads `var/`, so put your database on the server once with `pfdk db:push`.
3. If the host runs an older PHP, set `"config": {"platform": {"php": "8.2"}}` in the project's `composer.json`.
4. Run `pfdk deploy --dry`, check the file list, then `pfdk deploy`.

## Commands

| Command | What it does |
| --- | --- |
| `pfdk init [preset]` | Install a preset (asks if you give none) |
| `pfdk deploy [--dry] [--pull]` | Run dg. For Symfony projects with a database it offers to migrate first |
| `pfdk backup` | Download the live database into the backup folder |
| `pfdk db:pull` | Replace the local database with the live one (the local one is kept) |
| `pfdk db:push [-y]` | Overwrite the live database with the local one (the live one is saved first) |
| `pfdk db:migrate [-y]` | Symfony: pull, run the Doctrine migrations locally, push back |
| `pfdk exec -- <command>` | Run a command in the project, through DDEV when it runs |

`-c <ini>` picks the ini. Without it pfdk uses `deploy.ini` or the only `deploy*.ini` one level down, and asks if there are several. A connection or login error always exits non-zero, so a failing backup aborts a deploy.

## Configuration

One `deploy.ini`, which dg reads too (dg ignores the `pfdk_*` keys).

| Key | Meaning |
| --- | --- |
| `pfdk_db` | SQLite file, relative to the project and to the remote root. Enables the database commands |
| `pfdk_db_local` | Local SQLite file if it differs from the live one. Default: same as `pfdk_db` |
| `pfdk_backups` | Backup folder (default `backups`) |
| `pfdk_backups_keep` | Keep the newest N pfdk backups (default 10, `0` keeps all) |
| `pfdk_retries` | Attempts for a transfer that fails to connect or times out (default 3, `1` = no retry) |
| `pfdk_finally[]` | Steps that run after every deploy, also a failed one. See [Cleanup steps](#cleanup-steps) |
| `pfdk_type = symfony` | Enables `db:migrate` |
| `pfdk_migrate_command` | Replaces the default `php bin/console doctrine:migrations:migrate --no-interaction` |
| `pfdk_ddev = off` | Never use DDEV (default: use it when it runs) |

Building assets, clearing caches and the like are plain `before[]` / `after[]` jobs, see dg's documentation. `local` (`..` in the presets) is the project folder, and relative paths are resolved against it. The folder name `ftp-deployment` is only a convention.

## Databases

- Only **SQLite** files can be pulled and pushed over FTP. MariaDB and MySQL are not supported by these commands; deploying the code works for any app.
- `db:push` uploads to `<name>.pfdk-upload` first, checks the size, then renames it over the live file, so an aborted upload never leaves a truncated database. Some servers refuse to rename over an existing file; then pfdk deletes the old file right before renaming, as dg does. If that last rename fails, the complete new file stays on the server as `<name>.pfdk-upload`.
- `db:push` replaces the whole file. Run it when nobody writes to the live app.
- No remote shell: migrations run on your machine against a copy. Run `db:migrate` before deploying code that needs the new schema.
- **Different local and live file** (for example `var/data_dev.db` locally, `var/data_prod.db` live): set `pfdk_db_local`. `db:pull` replaces it, `db:push` uploads it, `db:migrate` migrates it. An empty value counts as not set.
- With `pfdk_db_local` the app's `DATABASE_URL` must point at that file, for example `sqlite:///%kernel.project_dir%/var/data_dev.db`. If the migration leaves the file unchanged, `db:migrate` warns and does not push, so an unmigrated copy never replaces the live database.

## Flaky connections

- `backup`, `db:pull` and `db:push` try again when the connection fails or times out (curl errors 7, 10, 28): up to `pfdk_retries` attempts, 3 s apart, with a short message on screen.
- Wrong password and missing files are never retried.

## Backups

- `backup`, `db:pull` and `db:push` save into `pfdk_backups`: `pfdk-database-<stamp>.db`, `pfdk-database-pulled-<stamp>.db` and `pfdk-database-pre-push-<stamp>.db`.
- `pfdk_backups_keep` deletes only files with that `pfdk-database-` prefix, oldest stamp first.
- Other files are never touched, including older pfdk backups named `database-*.db`. Remove those yourself.
- Data on the server damaged? Copy the newest backup over your local database and run `pfdk db:push`.

## Cleanup steps

`pfdk_finally[]` undoes what `before[]` did locally, for example restoring dev packages after `composer install --no-dev`. The Symfony presets use it for exactly that.

```ini
pfdk_finally[] = "php ftp-deployment/vendor/bin/pfdk exec -c ftp-deployment/deploy.ini -- composer install"
```

- Runs after every `pfdk deploy` once dg started: success, failure and `--dry`.
- All steps run even if one fails. The deploy's own result still counts, and a failing step makes the command exit non-zero.
- Also runs when dg could not connect (the exit code cannot tell that apart from a later failure).
- Does **not** run when pfdk stops before dg starts (failed `--pull` or migration).
- Ctrl-C and SIGTERM during dg are waited out so the steps still run. This needs the `pcntl` extension; without it an interrupted deploy skips them, so run them by hand.
- dg skips its own `after[]` steps when a deploy stops midway, which is why cleanup belongs here.

## DDEV

If the project has `.ddev/config.yaml` and DDEV is running, `pfdk exec` and the default migration go through `ddev exec`. Steps in `pfdk_finally[]` are plain shell commands in the shell pfdk runs in, with no DDEV detection of their own. Start a step with `pfdk exec --`, as the presets do, to follow the DDEV rule like `before[]` steps.

## Several targets

One ini per target (`deploy.staging.ini`, `deploy.production.ini`), or one ini with sections `[staging]` and `[production]` and `--section production`. With sections pfdk never guesses: it asks or fails listing them. Give each target its own `pfdk_backups` folder.

## Presets

`symfony` (production build, prod flag, cache purge, `.htaccess` files), `symfony-sqlite` (adds the database settings and a backup before the build) and `static`. Install your own from a folder: `pfdk init ./my-presets/wordpress`, see [CONTRIBUTING.md](CONTRIBUTING.md).

**Web root and `.htaccess` (Apache):** the Symfony presets put two editable files next to your `deploy.ini` and upload both. Which one does the work depends on your hosting:

- The web root points to `public/` (best): `htaccess.public` becomes `public/.htaccess` and sends requests to `index.php`.
- You can only put the whole folder into the web root: `htaccess.root` becomes `.htaccess` there and rewrites every request into `public/`, so `.env.prod.local`, `src/` and `var/` cannot be downloaded.

The file you do not need stays unused. If you want neither, delete the two `upload:` lines, the two `.htaccess` ignore entries and the files. The folder is called `public` in the files; change it there if yours is named differently. Other web servers than Apache need their own rules.

## Development

```bash
ddev start && ddev composer install && ddev exec vendor/bin/phpunit
```

Changes are specified with [OpenSpec](https://github.com/Fission-AI/OpenSpec) (`openspec/`), and the full test suite must pass before a change is archived. MIT licensed; use it, fork it, change it.
