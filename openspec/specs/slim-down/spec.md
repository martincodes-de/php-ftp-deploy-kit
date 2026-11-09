# slim-down Specification

## Purpose
TBD - created by archiving change slim-down. Update Purpose after archive.

## Requirements

### Requirement: No separate check command
pfdk SHALL NOT offer a `check` command. Commands that connect SHALL keep verifying protocol support first and fail with an actionable message.

#### Scenario: check is gone
- **WHEN** `pfdk check` is run
- **THEN** it is reported as an unknown command

#### Scenario: guard stays
- **WHEN** a connecting command runs against an unsupported protocol
- **THEN** it stops before any transfer with the list of problems

### Requirement: dg/ftp-deployment is found automatically
pfdk SHALL look for dg/ftp-deployment in the project's `vendor/` first and in its own `vendor/` second, and SHALL NOT read a tool path from the ini.

#### Scenario: project copy wins
- **WHEN** the project has `vendor/dg/ftp-deployment/deployment`
- **THEN** `deploy` runs that copy

#### Scenario: ini key ignored
- **WHEN** the ini contains `pfdk_tool`
- **THEN** it has no effect
