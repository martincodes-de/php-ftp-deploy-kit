## Why

dg/ftp-deployment treats `ftp://` as plain FTP and `ftps://` as FTP over TLS (explicit, via AUTH TLS on the normal port). pfdk did the opposite for its own transfers: it forced TLS for `ftp://`, so hosts without TLS failed, and it passed `ftps://` to curl, which means implicit TLS on port 990, a different connection type than dg uses.

## What Changes

- pfdk follows dg: `ftp://` is plain FTP, `ftps://` is explicit FTP over TLS.
- Commands that connect over plain FTP print a warning that the password is sent unencrypted.
- The `symfony-sqlite` preset recommends `ftps://`.

## Capabilities

### New Capabilities
- `ftp-security`: how the URL scheme maps to the connection type and what warning plain FTP gets.

### Modified Capabilities
None.

## Impact

`CurlRemoteFiles`, `backup`/`db:pull`/`db:push`, README, preset. Setups that used `ftp://` against a TLS-only host must switch to `ftps://`, as dg already required.
