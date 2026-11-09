# custom-presets Specification

## Purpose
TBD - created by archiving change custom-presets. Update Purpose after archive.

## Requirements

### Requirement: Presets from a folder path
`pfdk init <path>` SHALL install the preset in that folder when the argument contains a path separator. Plain names SHALL keep referring to bundled presets.

#### Scenario: own preset folder
- **WHEN** `pfdk init ./my-presets/wordpress` runs and the folder contains `deploy.ini.example`
- **THEN** its files are installed like those of a bundled preset

#### Scenario: missing folder
- **WHEN** the path does not exist
- **THEN** the command fails with `Unknown preset`

### Requirement: Custom presets can extend bundled ones
A custom preset's `extends` file SHALL name a bundled preset or a folder path (relative to the custom preset), and its snippets SHALL apply to the base's markers.

#### Scenario: extends a bundled preset
- **WHEN** a custom preset extends `symfony` and provides `before-build.snippet`
- **THEN** the installed `deploy.ini.example` contains the snippet and the files of `symfony`

### Requirement: Every bundled preset is verified
Each bundled preset SHALL install and its `deploy.ini.example` SHALL be loadable by the config parser, so a contributed preset cannot ship a broken ini unnoticed.

#### Scenario: broken preset ini
- **WHEN** a bundled preset's ini has no valid `remote`
- **THEN** the test suite fails
