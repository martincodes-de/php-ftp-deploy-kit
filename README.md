# PHP FTP Deploy Kit (`pfdk`)

A thin layer over [dg/ftp-deployment](https://github.com/dg/ftp-deployment) for PHP apps on FTP-only hosting.

- **One config file:** your `deploy.ini`. dg/ftp-deployment reads it, and `pfdk` reads the same file.
- **Presets:** ready-made `deploy.ini.example` files, e.g. for Symfony with SQLite.
- **Database commands:** back up, pull and push a SQLite database file over FTP, always backing up what gets overwritten. MariaDB/MySQL are not supported by these commands (deploying the code works for any app).
- **Your steps stay yours:** build, cache and similar steps are plain `before[]`/`after[]` jobs in the ini.

## When to use it

Use it when your only way onto the server is FTP, as with classic shared hosts such as bplaced: no SSH, no Composer on the server, but you want to ship a PHP app quickly. Everything is built on your machine (`composer install --no-dev`, assets, migrations) and uploaded as it is; `pfdk` handles the parts around it: backups, database pull/push and Symfony migrations.

dg/ftp-deployment itself can trigger server-side steps without a shell: its `before[]`, `afterUpload[]` and `after[]` jobs can call a script on your server over HTTP, and `remote:` jobs cover simple file operations such as `unzip` and `chmod`. Those stay your own jobs in the ini. `pfdk` runs migrations locally on a copy of the database, so they do not depend on anything running on the server.

If you have SSH on the server and want to run arbitrary commands there, a tool built for that (for example Deployer) is the better fit.

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

Add `ftp-deployment/deploy.ini`, `ftp-deployment/backups/`, `ftp-deployment/deploy.log` and `ftp-deployment/vendor/` to your `.gitignore`, and fill in `remote = "ftps://USER:PASSWORD@HOST/PATH"`.

| Scheme | Meaning |
| --- | --- |
| `ftps://` | FTP over TLS (recommended) |
| `ftp://` | Plain FTP: the password is sent unencrypted, `pfdk` warns about it. Use it only if the host offers nothing else. |
| `sftp://` | SFTP: needs the `ssh2` PHP extension for the deployment and a curl built with libssh2 for the database commands |
| `file:///path` | A local folder, handy for rehearsing a deploy |

Check once after installing that this PHP can handle your protocol:

```bash
php ftp-deployment/vendor/bin/pfdk check -c ftp-deployment/deploy.ini
```

Every command that connects runs the same check first and stops with a clear message if something is missing.

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
| `pfdk exec -- <command>` | Run a command in the project folder, through DDEV when it is running (used by the presets for `composer install`). The words are joined with spaces and run by a shell, so quotes you type in your own shell are already gone; quote inside the command if needed. |
| `pfdk check` | Show what this PHP supports and whether your protocol works |

All commands accept `-c path/to/deploy.ini` (default: `./deploy.ini`).

A connection or login error always stops the command with a non-zero exit code, so a failing backup step aborts a deploy. A database that does not exist on the server yet is not an error (first deploy).

### DDEV

If the project folder contains `.ddev/config.yaml` and the DDEV project is running, `pfdk exec` and the default migration command of `db:migrate` run through `ddev exec` automatically; otherwise they run on your host. Set `pfdk_ddev = off` to always use the host. A `pfdk_migrate_command` you set yourself is run exactly as written.

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
pfdk_migrate_command = php bin/console doctrine:migrations:migrate --no-interaction
                            ; optional: replaces the default command and is run exactly as written
pfdk_ddev = off             ; optional: never use DDEV, even if it is running (default: use it when it runs)
```

## Presets

- `symfony-sqlite`: DB backup, production `composer install` (through DDEV when it runs), `APP_ENV=prod` flag file, cache purge, dev dependencies restored afterwards.
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
