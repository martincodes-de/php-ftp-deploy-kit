## ADDED Requirements

### Requirement: Folder name is configurable
`pfdk init` SHALL replace every occurrence of `ftp-deployment` in the files it copies with the name of the current folder.

#### Scenario: custom folder
- **WHEN** `pfdk init symfony-sqlite` runs inside a folder named `deploy`
- **THEN** the copied `deploy.ini.example` refers to `deploy/vendor/bin/pfdk` and `deploy/deploy.ini`

#### Scenario: conventional folder
- **WHEN** it runs inside `ftp-deployment`
- **THEN** the files are copied unchanged

### Requirement: Symfony preset ignores test cache
The `symfony-sqlite` preset SHALL list `.phpunit.result.cache` in `ignore` and offer `pfdk_type = symfony` as a commented-out line.

#### Scenario: preset content
- **WHEN** the preset is copied
- **THEN** the ignore list contains `.phpunit.result.cache` and `pfdk_type` is present only as a comment
