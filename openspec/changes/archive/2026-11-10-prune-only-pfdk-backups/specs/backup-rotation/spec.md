## MODIFIED Requirements

### Requirement: Keep only the newest backups
After creating a backup, pfdk SHALL delete the oldest files (by the stamp in the name, then by name; never by file time, never directories) named `pfdk-database-(pulled-|pre-push-)?YYYYMMDD-HHMMSS.db` in the backup folder so that at most `pfdk_backups_keep` remain. The default is 10. A value of 0 SHALL disable deletion.

#### Scenario: more backups than allowed
- **WHEN** the backup folder holds 4 old backups, `pfdk_backups_keep = 2`, and a new backup is created
- **THEN** the new backup and the newest old one remain and the other 3 are deleted

#### Scenario: rotation disabled
- **WHEN** `pfdk_backups_keep = 0`
- **THEN** no backup is deleted

### Requirement: Rotation only touches pfdk backups
Rotation SHALL NOT delete files or directories that do not match the exact pattern `pfdk-database-(pulled-|pre-push-)?YYYYMMDD-HHMMSS.db`.

#### Scenario: foreign file in the folder
- **WHEN** the backup folder also holds `notes.txt` or `database-20240101-000001.db` or `database-pulled-manual.db`
- **THEN** all three are kept regardless of `pfdk_backups_keep`
