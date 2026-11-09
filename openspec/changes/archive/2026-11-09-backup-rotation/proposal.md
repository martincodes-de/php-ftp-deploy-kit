## Why

Every backup, pull and push adds a file to the backup folder, so it grows without bound. The backup folder should be configurable and keep only the newest files.

## What Changes

- New ini key `pfdk_backups_keep` (default 10; 0 keeps everything).
- After each backup, pull or push, pfdk deletes the oldest `database-*.db` files beyond that number.

## Capabilities

### New Capabilities
- `backup-rotation`: how many backups are kept and which files rotation may delete.

### Modified Capabilities
None.

## Impact

`DeployConfig`, `BackupCommand`, `DbPullCommand`, `DbPushCommand`, README. Existing setups start rotating at 10 files.
