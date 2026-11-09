# PHP FTP Deploy Kit (`pfdk`)

A thin layer over [dg/ftp-deployment](https://github.com/dg/ftp-deployment) for PHP apps on FTP-only hosting.

- **One config file:** your `deploy.ini`. dg/ftp-deployment reads it, and `pfdk` reads the same file.
- **Presets:** ready-made `deploy.ini.example` files, e.g. for Symfony with SQLite.
- **Database commands:** back up, pull and push a database file over FTP, always backing up what gets overwritten.
- **Your steps stay yours:** build, cache and similar steps are plain `before[]`/`after[]` jobs in the ini.

## When to use it

Use it when your only way onto the server is FTP, as with classic shared hosts such as bplaced: no SSH, no Composer on the server, but you want to ship a PHP app quickly. Everything is built on your machine (`composer install --no-dev`, assets, migrations) and uploaded as it is; `pfdk` handles the parts around it: backups, database pull/push and Symfony migrations.

If you have SSH on the server, a tool that runs commands there (for example Deployer) is the better fit.

`db:push` replaces the whole database file, so run it when nobody is writing to the live app, for example together with `db:migrate`.

## Why

I like Nette's FTP deployment tool and use it for my apps. The same standard use cases kept coming back (back up, pull and push the database, run steps around a deploy), so this package puts them on top of it. Use it, fork it, change it; contributions are welcome.

## Install

Requires PHP 8.2+ with `ext-curl`.

Install pfdk in its own folder inside your project. A deploy runs `composer install --no-dev`, which would otherwise remove the tool that is running it.

```bash
mkdir ftp-deployment && cd ftp-deployment
composer init --no-interaction --name=my/ftp-deployment
composer require martincodes-de/php-ftp-deploy-kit
php vendor/bin/pfdk init symfony-sqlite
cp deploy.ini.example deploy.ini
```

Add `ftp-deployment/deploy.ini`, `ftp-deployment/backups/`, `ftp-deployment/deploy.log` and `ftp-deployment/vendor/` to your `.gitignore`, and fill in `remote = "ftp://USER:PASSWORD@HOST/PATH"` (use `ftps://` or `sftp://` if the host supports it).

Run all commands from the project root:

```bash
php ftp-deployment/vendor/bin/pfdk deploy --dry -c ftp-deployment/deploy.ini
```

The folder name `ftp-deployment` is only a convention. `pfdk init` rewrites the paths in the preset to the name of the folder you run it in.

The ini's `local` key (`..` in the preset) is the project folder. `pfdk_db` and a relative `pfdk_backups` are resolved against it.

## Commands

| Command | What it does |
| --- | --- |
| `pfdk init [preset]` | List presets, or copy one into the current folder (never overwrites files) |
| `pfdk deploy [--dry] [--pull]` | Run dg/ftp-deployment; `--dry` previews, `--pull` runs `git pull --ff-only` first |
| `pfdk backup` | Download the live database into the backup folder |
| `pfdk db:pull` | Replace the local database with the live one (local one is kept as `.bak-local-<time>`) |
| `pfdk db:push [-y]` | Overwrite the live database with the local one (live one is saved in the backup folder first) |
| `pfdk db:migrate [-y]` | Symfony only: pull the live database, run the Doctrine migrations locally, push it back |

All commands accept `-c path/to/deploy.ini` (default: `./deploy.ini`).

A connection or login error always stops the command with a non-zero exit code, so a failing backup step aborts a deploy. A database that does not exist on the server yet is not an error (first deploy).

### Migrations on FTP-only hosting

There is no remote shell, so migrations run on your machine against a copy of the live database. Enable it with `pfdk_type = symfony` and run it before deploying code that needs the new schema:

```bash
php ftp-deployment/vendor/bin/pfdk db:migrate -c ftp-deployment/deploy.ini
php ftp-deployment/vendor/bin/pfdk deploy -c ftp-deployment/deploy.ini
```

If the pull or the migration fails, nothing is pushed and the live database stays untouched.

## Extra ini keys

dg/ftp-deployment ignores keys it does not know, so `pfdk` keeps its settings in the same file:

```ini
pfdk_db = var/database.db   ; database file, relative to the project and to the remote root
pfdk_backups = backups      ; where downloaded backups go (default: backups)
pfdk_backups_keep = 10      ; keep the newest N backups, delete older database-*.db files (0 = keep all, default: 10)
pfdk_tool = path/to/deployment  ; optional: use a specific dg/ftp-deployment binary
pfdk_type = symfony         ; optional: enables db:migrate
pfdk_migrate_command = ddev exec php bin/console doctrine:migrations:migrate --no-interaction
                            ; optional: replaces the default `php bin/console doctrine:migrations:migrate --no-interaction`
```

## Presets

- `symfony-sqlite`: production composer install, DB backup before upload, `APP_ENV=prod` flag file, cache purge, dev dependencies restored afterwards.
- `static`: upload a built folder.

Presets are plain files in `presets/<name>/`; add a folder to add a preset.

## Development

Runs in DDEV without a database container:

```bash
ddev start
ddev composer install
ddev exec vendor/bin/phpunit
```

Changes are specified with [OpenSpec](https://github.com/Fission-AI/OpenSpec) (`openspec/`). The full test suite must pass before a change is archived.
