# deploy-finally Specification

## Purpose
Restore the local project after a deploy, even a failed one.

## Requirements

### Requirement: Finally steps run after every deploy
`pfdk deploy` SHALL run each `pfdk_finally[]` command in the project directory after dg returned, whether dg succeeded, failed or ran with `--dry`. It SHALL run all steps even if one fails. It SHALL NOT run them when dg was never started.

#### Scenario: failed deploy
- **WHEN** dg exits non-zero
- **THEN** the finally steps run and the command exits non-zero

#### Scenario: dry run
- **WHEN** `pfdk deploy --dry` runs
- **THEN** the finally steps run

#### Scenario: dg never started
- **WHEN** `--pull` fails before dg starts
- **THEN** no finally step runs

### Requirement: The deploy result is never hidden
A failing finally step SHALL be reported and make the command exit non-zero, and SHALL NOT replace or hide the output of a failed deploy.

#### Scenario: both fail
- **WHEN** dg and a finally step fail
- **THEN** both are visible in the output and the exit code is non-zero
