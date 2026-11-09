# optional-features Specification

## Purpose
TBD - created by archiving change optional-features. Update Purpose after archive.

## Requirements

### Requirement: Opt out of a feature
`pfdk init <preset> --without=<feature>` SHALL omit every block between `; @pfdk:optional-start <feature>` and `; @pfdk:optional-end <feature>` and every preset file whose name starts with `<feature>.`.

#### Scenario: no htaccess
- **WHEN** `pfdk init symfony --without=htaccess` runs
- **THEN** `htaccess.root` and `htaccess.public` are not created, and the installed ini has neither the `.htaccess` upload jobs nor the `.htaccess` ignore entries

#### Scenario: feature included by default
- **WHEN** `pfdk init symfony` runs
- **THEN** the files and the blocks are present and no marker lines remain

### Requirement: Several features and unknown names
`--without` SHALL be repeatable. A feature name that no part of the preset uses SHALL be ignored.

#### Scenario: unknown feature
- **WHEN** `--without=nothing` is given
- **THEN** the installation is identical to one without the option
