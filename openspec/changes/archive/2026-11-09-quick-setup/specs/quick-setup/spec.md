## ADDED Requirements

### Requirement: init creates the working ini
After copying a preset, `pfdk init` SHALL create `deploy.ini` as a copy of `deploy.ini.example` unless `deploy.ini` already exists.

#### Scenario: fresh folder
- **WHEN** `pfdk init symfony` runs in an empty folder
- **THEN** both `deploy.ini.example` and `deploy.ini` exist with the same content

#### Scenario: existing ini is kept
- **WHEN** `deploy.ini` already exists
- **THEN** it is not changed

### Requirement: init updates the project's gitignore
When the folder's parent has a `.git` directory, `pfdk init` SHALL add a block between the markers `###> pfdk ###` and `###< pfdk ###` to the parent's `.gitignore`, listing `deploy.ini`, `backups/`, `deploy.log` and `vendor/` of the folder. It SHALL NOT add the block twice. Without a git parent it SHALL print the lines to add instead.

#### Scenario: block added once
- **WHEN** `pfdk init` runs twice in `<project>/ftp-deployment`
- **THEN** `<project>/.gitignore` contains exactly one pfdk block with the paths prefixed by the folder name

#### Scenario: no git project
- **WHEN** the parent has no `.git`
- **THEN** no file is changed and the lines are printed

### Requirement: Interactive preset choice
`pfdk init` without a preset SHALL ask which preset to install when the terminal is interactive, and SHALL only list the presets otherwise.

#### Scenario: interactive
- **WHEN** `pfdk init` runs interactively and the user picks `symfony`
- **THEN** the `symfony` preset is installed

#### Scenario: non-interactive
- **WHEN** `pfdk init` runs without a preset and without interaction
- **THEN** the presets are listed and nothing is installed
