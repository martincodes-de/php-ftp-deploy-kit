## Why

The CLI reports a hard-coded version that was not updated for v0.4.0 and v0.5.0 (it still said 0.3.0). A literal in the code will always drift from the real release.

## What Changes

- `pfdk --version` reports the installed package version as Composer knows it.

## Capabilities

### New Capabilities
- `cli-version`: where the reported version comes from.

### Modified Capabilities
None.

## Impact

`ApplicationFactory` only.
