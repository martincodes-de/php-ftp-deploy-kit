## ADDED Requirements

### Requirement: Uploads replace the live file atomically
`upload()` SHALL write to `<name>.pfdk-upload` next to the target, verify that the remote size equals the local size, and only then rename it onto the target. If the server refuses the rename, it SHALL delete the target and rename again. On any failure it SHALL remove the temporary file and leave the target unchanged, except when the rename fails after the target was deleted: the complete temporary file SHALL then be kept and named in the error.

#### Scenario: size does not match
- **WHEN** the remote size differs from the local size
- **THEN** no rename happens, the temporary file is deleted and the target is unchanged

#### Scenario: server refuses to rename over an existing file
- **WHEN** the first rename fails
- **THEN** the target is deleted and the rename is repeated
