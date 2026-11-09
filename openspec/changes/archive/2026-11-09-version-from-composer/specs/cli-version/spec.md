## ADDED Requirements

### Requirement: Version comes from Composer
The version shown by the CLI SHALL be the one Composer recorded for the pfdk package, not a literal in the code.

#### Scenario: installed release
- **WHEN** pfdk is installed as `v0.5.1`
- **THEN** `pfdk --version` reports `v0.5.1`

#### Scenario: unknown version
- **WHEN** Composer has no version for the package
- **THEN** the CLI reports `dev`
