# ftp-security Specification

## Purpose
TBD - created by archiving change ftp-tls-semantics. Update Purpose after archive.

## Requirements

### Requirement: Scheme decides the connection type
pfdk SHALL connect the same way dg/ftp-deployment does: `ftp://` as plain FTP and `ftps://` as explicit FTP over TLS. TLS SHALL be required for `ftps://`.

#### Scenario: ftps uses explicit TLS
- **WHEN** the remote is `ftps://host/path`
- **THEN** pfdk requests `ftp://host/path` from curl with TLS required, not an implicit-TLS `ftps://` URL

#### Scenario: ftp stays plain
- **WHEN** the remote is `ftp://host/path`
- **THEN** pfdk does not require TLS

### Requirement: Plain FTP is announced
Commands that connect to a remote over plain FTP SHALL print a warning that the password is sent unencrypted and name `ftps://` as the alternative.

#### Scenario: warning on backup
- **WHEN** `pfdk backup` runs against an `ftp://` remote
- **THEN** the output contains the warning

#### Scenario: no warning for TLS
- **WHEN** the remote is `ftps://`, `sftp://` or `file://`
- **THEN** no warning is printed
