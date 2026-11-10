## ADDED Requirements

### Requirement: Uploads replace the live file atomically
`upload()` SHALL write to `<name>.pfdk-upload` next to the target, verify that the remote size equals the local size, and only then rename it onto the target. Only the transfer of the temporary file MAY be repeated on connection errors; size checks, the rename and every delete SHALL run once. If the server reports no size (SIZE unsupported), the upload SHALL be aborted instead of renamed unchecked.

After a connection error during the rename, pfdk SHALL read the state back: the target has the local size and the temporary file is gone means success; the temporary file is still there means the rename did not happen, so the temporary file is removed and the target is untouched; if the state cannot be read, pfdk SHALL abort with a message naming target and temporary file and SHALL delete nothing.

The target SHALL only be deleted as a fallback when the server explicitly refuses the rename (curl 21) and the temporary file was confirmed complete. If the rename fails after that delete, the complete temporary file SHALL be kept and named in the error, but only if it was confirmed to exist. In all other failures the temporary file SHALL be removed (one attempt) and the target stays unchanged.

#### Scenario: size does not match
- **WHEN** the remote size differs from the local size
- **THEN** no rename happens, the temporary file is deleted and the target is unchanged

#### Scenario: server reports no size
- **WHEN** SIZE returns no value
- **THEN** the upload is aborted with "Server reports no file size", nothing is renamed

#### Scenario: rename answer lost after it was carried out
- **WHEN** the rename succeeds on the server but the answer times out
- **THEN** the state check finds the new file at the target, the upload counts as done and nothing is deleted

#### Scenario: connection error never deletes the target
- **WHEN** the rename fails with a connection error
- **THEN** the target is never deleted

#### Scenario: server refuses to rename over an existing file
- **WHEN** the rename is refused with a server error and the temporary file is confirmed complete
- **THEN** the target is deleted and the rename is repeated once

#### Scenario: file target with special characters
- **WHEN** a `file://` target name contains `#`, `?` or `%`
- **THEN** the file is written under exactly that name
