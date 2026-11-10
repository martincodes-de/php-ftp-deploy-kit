## Why

Rotation deleted every file matching `database-*.db` in the backup folder, including backups a project kept itself (for example `database-pulled-<other-format>.db` from old Makefile targets).

## What Changes

- Rotation only considers the names pfdk writes: `database-`, an optional `pulled-` or `pre-push-`, then `YYYYMMDD-HHMMSS`, then `.db`.

## Capabilities

### New Capabilities
None.

### Modified Capabilities
- `backup-rotation`: narrower match.

## Impact

`BaseCommand::pruneBackups()`, README. Backups named exactly like pfdk's still count as pfdk's own; `pfdk_backups_keep = 0` or a separate folder avoids that.
