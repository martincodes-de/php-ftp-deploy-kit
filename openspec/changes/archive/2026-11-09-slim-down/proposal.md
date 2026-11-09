## Why

pfdk should stay a thin layer. The `check` command only repeats information that the automatic pre-connection check already reports when something is wrong, and `pfdk_tool` overrides the location of dg/ftp-deployment, which pfdk finds on its own.

## What Changes

- **BREAKING** Remove the `pfdk check` command; the environment check before connecting stays.
- **BREAKING** Remove the `pfdk_tool` ini key; dg/ftp-deployment is looked up in the project's and pfdk's own `vendor/`.

## Capabilities

### New Capabilities
- `slim-down`: what pfdk deliberately does not offer.

### Modified Capabilities
None.

## Impact

`CheckCommand`, `Requirements::describe()`, `DeployConfig`, `DeployCommand`, their tests and the README.
