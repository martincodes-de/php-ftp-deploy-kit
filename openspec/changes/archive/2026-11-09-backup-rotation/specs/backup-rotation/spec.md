## ADDED Requirements

### Requirement: Keep only the newest backups
After creating a backup, pfdk SHALL delete the oldest files matching `database-*.db` in the backup folder so that at most `pfdk_backups_keep` remain. The default is 10. A value of 0 SHALL disable deletion.

#### Scenario: more backups than allowed
- **WHEN** the backup folder holds 4 old backups, `pfdk_backups_keep = 2`, and a new backup is created
- **THEN** the new backup and the newest old one remain and the other 3 are deleted

#### Scenario: rotation disabled
- **WHEN** `pfdk_backups_keep = 0`
- **THEN** no backup is deleted

### Requirement: Rotation only touches pfdk backups
Rotation SHALL NOT delete files that do not match `database-*.db`.

#### Scenario: foreign file in the folder
- **WHEN** the backup folder also holds `notes.txt`
- **THEN** `notes.txt` is kept regardless of `pfdk_backups_keep`

### Requirement: Rotation after every kind of backup
`backup`, `db:pull` and `db:push` SHALL each rotate the backup folder after saving their file.

#### Scenario: pull rotates
- **WHEN** `db:pull` saves its copy and the folder exceeds the limit
- **THEN** the oldest files are deleted
