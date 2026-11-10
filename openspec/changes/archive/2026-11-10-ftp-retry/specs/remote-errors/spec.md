## ADDED Requirements

### Requirement: Retry connection failures
A transfer that fails with a connection or timeout error (curl 7, 10, 28, and 55 or 56 in the middle of a download or of the upload of the temporary file; those two only there, because a repeated send or receive is harmless, while a repeated rename or delete is not) SHALL be attempted again, up to `pfdk_retries` attempts in total (1 to 10, default 3; anything else is a configuration error), with a pause of 3 seconds and a message that does not contain credentials. Login errors and missing files SHALL NOT be retried. A download attempt SHALL start with an empty file.

#### Scenario: timeout, then success
- **WHEN** the first attempt times out and the second succeeds
- **THEN** the command succeeds and one retry message was shown

#### Scenario: all attempts fail
- **WHEN** every attempt times out
- **THEN** the command fails with "failed after N attempts" and the last error

#### Scenario: login denied
- **WHEN** the server rejects the login
- **THEN** there is no second attempt
