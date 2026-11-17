## ADDED Requirements

### Requirement: Uploads create missing folders
`upload()` SHALL create the folder of the target, including missing parents, when it does not exist, for every scheme. If the folder cannot be created, the upload SHALL fail and write nothing.

#### Scenario: first push into a fresh remote
- **WHEN** the remote has no `var/database` folder and the database is pushed to `var/database/database.db`
- **THEN** the folders are created and the file is uploaded

#### Scenario: folder cannot be created
- **WHEN** a part of the target folder path is an existing file
- **THEN** the upload fails with a message and no file is written
