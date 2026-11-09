## ADDED Requirements

### Requirement: Database-free Symfony preset
The `symfony` preset SHALL not set `pfdk_db` and SHALL not run `pfdk backup`.

#### Scenario: no database steps
- **WHEN** the preset's ini is read
- **THEN** it contains no `pfdk_db` key and none of its jobs calls `pfdk backup`

### Requirement: Production build steps
The preset SHALL install production dependencies with `APP_ENV=prod`, ship a prod flag file as `.env.local`, purge `var/cache` and restore dev dependencies afterwards, all local steps through `pfdk exec`. Compiling AssetMapper assets SHALL be offered as a commented-out job, because not every Symfony app uses AssetMapper and the command fails without it.

#### Scenario: build order
- **WHEN** the `before[]` jobs are read in order
- **THEN** the production `composer install` is the first job

#### Scenario: assets are opt-in
- **WHEN** the preset's ini is read
- **THEN** the `asset-map:compile` job is present only as a comment

### Requirement: Compiled assets do not stay locally
The preset SHALL remove `public/assets` locally after the deploy, so the development server does not keep serving stale compiled files.

#### Scenario: cleanup job
- **WHEN** the `after[]` jobs are read
- **THEN** one of them removes `public/assets` through `pfdk exec`

### Requirement: TLS recommended
The preset SHALL use an `ftps://` remote.

#### Scenario: remote scheme
- **WHEN** the preset's ini is read
- **THEN** `remote` starts with `ftps://`
