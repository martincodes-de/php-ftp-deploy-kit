# PHP FTP Deploy Kit (`pfdk`)

Set up a deployment for a PHP app on FTP-only hosting in a couple of minutes, and keep it up to date. A thin layer over [dg/ftp-deployment](https://github.com/dg/ftp-deployment): presets, a safe database pull/push for SQLite, and a few helpers. The deploying itself is done by dg.

Right for classic shared hosts (bplaced and similar): no SSH, no Composer on the server. You build on your machine and upload the result. With SSH access, a tool that runs commands on the server (for example Deployer) fits better.

Ready-made for **Symfony** (with or without SQLite). Other setups such as Laravel or WordPress are welcome as presets, see [CONTRIBUTING.md](CONTRIBUTING.md).

## Quick start

Needs PHP 8.2+ with `ext-curl`. Install pfdk in its own folder, so that `composer install --no-dev` during a deploy cannot remove it.

```bash
mkdir ftp-deployment && cd ftp-deployment
composer require martincodes-de/php-ftp-deploy-kit
php vendor/bin/pfdk init symfony          # or: symfony-sqlite, static
```

`init` creates `deploy.ini` and ignores it in git. Put your connection into it (`remote = "ftps://USER:PASSWORD@HOST/PATH"`), then from the project root:

```bash
php ftp-deployment/vendor/bin/pfdk deploy --dry     # preview
php ftp-deployment/vendor/bin/pfdk deploy
```

| Scheme | Meaning |
| --- | --- |
| `ftps://` | FTP over TLS (recommended) |
| `ftp://` | Plain FTP, password unencrypted; `pfdk` warns |
| `sftp://` | Needs the `ssh2` PHP extension and curl built with libssh2 |
| `file:///path` | A local folder, to rehearse a deploy |

## First deploy, and when something goes wrong

Before the first deploy of a Symfony app:

1. Create `.env.prod.local` in the project root (git ignores it, the deploy uploads it) with the production secrets: `APP_SECRET` (for example `php -r 'echo bin2hex(random_bytes(16));'`) and your API keys.
2. With SQLite: the deploy never uploads `var/`, so put your database on the server once with `pfdk db:push`.
3. Build and upload run on your machine with your local PHP. If the host runs an older PHP, set `"config": {"platform": {"php": "8.2"}}` in the project's `composer.json`, so Composer installs packages that run there.
4. Run `pfdk deploy --dry`, check the file list, then `pfdk deploy`.

If a deploy stops midway, the `after[]` steps do not run and your local project stays in production mode. Restore it with `pfdk exec -- composer install` (and `pfdk exec -- rm -rf public/assets` if you compile assets). If data on the server is damaged, copy the newest file from the backup folder over your local database and run `pfdk db:push`.

## Commands

| Command | What it does |
| --- | --- |
| `pfdk init [preset]` | Install a preset (asks if you give none) |
| `pfdk deploy [--dry] [--pull]` | Run dg/ftp-deployment. For Symfony projects with a database it offers to migrate first |
| `pfdk backup` | Download the live database into the backup folder |
| `pfdk db:pull` | Replace the local database with the live one (the local one is kept) |
| `pfdk db:push [-y]` | Overwrite the live database with the local one (the live one is saved first) |
| `pfdk db:migrate [-y]` | Symfony: pull the live database, run the Doctrine migrations locally, push it back |
| `pfdk exec -- <command>` | Run a command in the project, through DDEV when it runs |

`-c <ini>` picks the ini; without it `pfdk` uses `deploy.ini` or the only `deploy*.ini` it finds one level down, and asks if there are several. A connection or login error always stops the command with a non-zero exit code, so a failing backup aborts a deploy.

**Databases:** only a SQLite file can be pulled and pushed over FTP. MariaDB/MySQL are not supported by these commands; deploying the code works for any app. `db:push` replaces the whole file, so run it when nobody writes to the live app. There is no remote shell, so migrations run on your machine against a copy; run `db:migrate` before deploying code that needs the new schema.

## Configuration

Everything is in one `deploy.ini`, which dg reads too (dg ignores the `pfdk_*` keys).

| Key | Meaning |
| --- | --- |
| `pfdk_db` | SQLite file, relative to the project and to the remote root. Enables the database commands |
| `pfdk_db_local` | Optional local SQLite file when it differs from the live one (for example `var/data_dev.db` locally, `pfdk_db = var/data_prod.db` live). `db:pull` replaces it, `db:push` uploads it, `db:migrate` migrates it. Default: same path as `pfdk_db` |
| `pfdk_backups` | Folder for downloaded backups (default `backups`) |
| `pfdk_backups_keep` | Keep the newest N backups, delete older ones (default 10, `0` keeps all) |
| `pfdk_type = symfony` | Enables `db:migrate` |
| `pfdk_migrate_command` | Replaces the default `php bin/console doctrine:migrations:migrate --no-interaction`; run exactly as written |
| `pfdk_ddev = off` | Never use DDEV (default: use it when it runs) |

Steps like building assets or clearing caches are plain `before[]`/`after[]` jobs in the ini, see dg's documentation. The ini's `local` key (`..` in the presets) is the project folder; `pfdk_db` and relative paths are resolved against it. The folder name `ftp-deployment` is only a convention: `init` adapts the preset to the folder it runs in.

**Several targets:** either one ini per target (`deploy.staging.ini`, `deploy.production.ini`), or one ini with sections `[staging]` and `[production]` and `--section production`. With sections, `pfdk` never guesses: it asks, or fails listing them. Give each target its own `pfdk_backups` folder.

**DDEV:** if the project has `.ddev/config.yaml` and DDEV is running, `pfdk exec` and the default migration go through `ddev exec` automatically. The presets use `pfdk exec` for `composer install`, so deploys work with and without DDEV.

## Presets

`symfony` (production build, prod flag, cache purge, `.htaccess` files), `symfony-sqlite` (adds the database settings and a backup before the build) and `static`. Add your own as a folder, see [CONTRIBUTING.md](CONTRIBUTING.md); `pfdk init ./my-presets/wordpress` installs one from a path.

**Web root and `.htaccess` (Apache):** the Symfony presets put two editable files next to your `deploy.ini` and upload both. Which one does the work depends on your hosting:

- The web root points to `public/` (best): `htaccess.public` becomes `public/.htaccess` and sends requests to `index.php`.
- You can only put the whole folder into the web root: `htaccess.root` becomes `.htaccess` there and rewrites every request into `public/`, so `.env.prod.local`, `src/` and `var/` cannot be downloaded.

The file you do not need stays unused. If you want neither, delete the two `upload:` lines, the two `.htaccess` ignore entries and the files. The folder is called `public` in the files; change it there if yours is named differently. Other web servers than Apache need their own rules.

## Development

```bash
ddev start && ddev composer install && ddev exec vendor/bin/phpunit
```

Changes are specified with [OpenSpec](https://github.com/Fission-AI/OpenSpec) (`openspec/`), and the full test suite must pass before a change is archived. MIT licensed; use it, fork it, change it.
