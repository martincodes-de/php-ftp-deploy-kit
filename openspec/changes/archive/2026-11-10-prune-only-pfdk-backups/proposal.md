## Why

Rotation deleted every file matching `database-*.db` in the backup folder, including backups a project kept itself (for example `database-pulled-<other-format>.db` from old Makefile targets).

## What Changes

- pfdk's own backups are named `pfdk-database-[pulled-|pre-push-]<stamp>.db` and only those are rotated. Older `database-*.db` files are never deleted.

## Capabilities

### New Capabilities
None.

### Modified Capabilities
- `backup-rotation`: narrower match.

## Impact

`BaseCommand::pruneBackups()`, README. Old-format backups stay in the folder forever (safe side).
