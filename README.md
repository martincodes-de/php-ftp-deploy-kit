# PHP FTP Deploy Kit (`pfdk`)

A thin layer over [dg/ftp-deployment](https://github.com/dg/ftp-deployment) for small PHP apps on FTP-only hosting.

- **One config file:** your `deploy.ini`. dg/ftp-deployment reads it, and `pfdk` reads the same file.
- **Presets:** ready-made `deploy.ini.example` files, e.g. for Symfony with SQLite.
- **Database commands:** back up, pull and push a database file over FTP, always backing up what gets overwritten.
- **Your steps stay yours:** build, cache and similar steps are plain `before[]`/`after[]` jobs in the ini.

## Why

I like Nette's FTP deployment tool and use it for my small apps. The same standard use cases kept coming back (back up, pull and push the database, run steps around a deploy), so this package puts them on top of it. Use it, fork it, change it; contributions are welcome.

## Install

Requires PHP 8.2+ with `ext-curl`.

```bash
composer config repositories.pfdk vcs https://github.com/martincodes-de/php-ftp-deploy-kit
composer require --dev martincodes-de/php-ftp-deploy-kit
vendor/bin/pfdk init symfony-sqlite
cp deploy.ini.example deploy.ini      # keep deploy.ini out of git
```

Fill in `remote = "ftp://USER:PASSWORD@HOST/PATH"` (use `ftps://` or `sftp://` if the host supports it).

## Commands

| Command | What it does |
| --- | --- |
| `pfdk init [preset]` | List presets, or copy one into the current folder (never overwrites files) |
| `pfdk deploy [--dry] [--pull]` | Run dg/ftp-deployment; `--dry` previews, `--pull` runs `git pull --ff-only` first |
| `pfdk backup` | Download the live database into `backups/` |
| `pfdk db:pull` | Replace the local database with the live one (local one is kept as `.bak-local-<time>`) |
| `pfdk db:push [-y]` | Overwrite the live database with the local one (live one is saved in `backups/` first) |

All commands accept `-c path/to/deploy.ini` (default: `./deploy.ini`).

## Extra ini keys

dg/ftp-deployment ignores keys it does not know, so `pfdk` keeps its settings in the same file:

```ini
pfdk_db = var/database.db   ; database file, relative to the project and to the remote root
pfdk_backups = backups      ; where downloaded backups go (default: backups)
pfdk_tool = path/to/deployment  ; optional: use a specific dg/ftp-deployment binary
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
