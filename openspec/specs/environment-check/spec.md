# environment-check Specification

## Purpose
TBD - created by archiving change environment-check. Update Purpose after archive.

## Requirements

### Requirement: Protocol support is verified before connecting
Before a command connects to the remote, pfdk SHALL verify that PHP's curl supports the configured protocol and fail with an actionable message if not. For `ftps://` curl SHALL support SSL. For `sftp://` curl SHALL list the `sftp` protocol. For `file://` curl SHALL list `file`.

#### Scenario: sftp without libssh2
- **WHEN** the remote is `sftp://…` and curl does not list `sftp`
- **THEN** the command stops before any transfer with a message that curl needs libssh2 support

#### Scenario: supported protocol
- **WHEN** curl supports the configured protocol
- **THEN** the command proceeds

### Requirement: Deploy verifies what dg needs
`pfdk deploy` SHALL additionally verify that the `ssh2` extension is loaded for `sftp://` and that the `openssl` extension is loaded for `ftps://`.

#### Scenario: sftp without ssh2 extension
- **WHEN** the remote is `sftp://…` and the `ssh2` extension is not loaded
- **THEN** `deploy` fails before running any job and says to install `ssh2` or use `ftps://`

### Requirement: Check command
`pfdk check` SHALL print the PHP and curl capabilities relevant to pfdk. When a config file is available it SHALL also report whether that config's protocol works, and SHALL exit non-zero if it does not.

#### Scenario: working config
- **WHEN** `pfdk check -c deploy.ini` runs and everything needed is present
- **THEN** it reports OK and exits with status 0

#### Scenario: no config file
- **WHEN** no ini exists
- **THEN** the general report is printed and the exit status is 0
