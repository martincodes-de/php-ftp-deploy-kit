## Why

`sftp://` only works if PHP's curl was built with libssh2 (for pfdk's database commands) and the `ssh2` extension is loaded (for dg's upload). `ftps://` needs TLS support. Today a missing piece shows up as an obscure error in the middle of a run.

## What Changes

- Before any command that connects, pfdk checks that the PHP environment can speak the configured protocol and stops with a clear message otherwise. `deploy` additionally checks what dg needs.
- New command `pfdk check` prints the environment report and exits non-zero on problems; run it once after installing.
- `ext-ssh2` is suggested in `composer.json`.

## Capabilities

### New Capabilities
- `environment-check`: which PHP features each protocol needs and when they are verified.

### Modified Capabilities
None.

## Impact

New `Requirements` class, `check` command, a guard in the remote commands and `deploy`, README, `composer.json` (suggest only).
