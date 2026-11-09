## ADDED Requirements

### Requirement: Presets can extend a base preset
A preset containing a file `extends` whose content is a preset name SHALL be installed as that base preset's files plus its own files. Its own files SHALL win over base files with the same name.

#### Scenario: base files are included
- **WHEN** `pfdk init symfony-sqlite` runs and `symfony-sqlite` extends `symfony`
- **THEN** the files of `symfony` (for example `env-prod-flag`) are created too

### Requirement: Markers and snippets
A base file MAY contain a line `; @pfdk:<name>`. A file `<name>.snippet` in the extending preset SHALL replace that line with its content. Snippet files SHALL NOT be copied into the project. Marker lines without a snippet SHALL be removed.

#### Scenario: snippet replaces marker
- **WHEN** the extending preset provides `before-build.snippet`
- **THEN** the installed `deploy.ini.example` contains the snippet's lines where the marker was and no marker lines

#### Scenario: no snippet
- **WHEN** a preset is installed without an extending preset
- **THEN** its marker lines are removed from the installed file

### Requirement: Invalid chains are rejected
A missing base preset or a cycle of `extends` SHALL fail with an error naming the preset.

#### Scenario: unknown base
- **WHEN** a preset extends a preset that does not exist
- **THEN** `pfdk init` fails with a message naming the missing base

### Requirement: symfony-sqlite builds on symfony
`symfony-sqlite` SHALL extend `symfony` and add only the database settings and a backup job that runs before the production build.

#### Scenario: backup before build
- **WHEN** `symfony-sqlite` is installed
- **THEN** the backup job comes before the production `composer install`
