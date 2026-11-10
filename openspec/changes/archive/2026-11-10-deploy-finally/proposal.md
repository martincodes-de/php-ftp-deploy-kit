## Why

When a deploy stops midway, dg skips `after[]`. The Symfony presets restore dev packages and remove compiled assets there, so a failed deploy left the local project in production mode.

## What Changes

- New list `pfdk_finally[]`: local shell commands that `pfdk deploy` runs after dg returns, on success, failure and `--dry` (decision: dry runs execute `before[]` steps, so they run it too).
- The Symfony presets move their restore steps from `after[]` to `pfdk_finally[]`.

## Capabilities

### New Capabilities
- `deploy-finally`

### Modified Capabilities
None.

## Impact

`DeployConfig`, `DeployCommand`, Symfony presets, README. Not run when nothing was deployed (failed `git pull` or migration), because no `before[]` step ran.
