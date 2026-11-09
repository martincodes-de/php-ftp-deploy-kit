# remote-config Specification

## Purpose
TBD - created by archiving change allow-file-remote. Update Purpose after archive.

## Requirements

### Requirement: Local folder remotes
pfdk SHALL accept a `file://` remote without a host and address files below that folder.

#### Scenario: file remote without host
- **WHEN** the ini has `remote = "file:///srv/target"`
- **THEN** the remote URL of `var/app.db` is `file:///srv/target/var/app.db`

#### Scenario: other schemes still need a host
- **WHEN** the ini has `remote = "ftp:///path"`
- **THEN** loading the config fails with a message that the remote must look like `ftp://host/path`
