## ADDED Requirements

### Requirement: Retry connection failures
A transfer that fails with a connection or timeout error (curl 7, 10, 28) SHALL be attempted again, up to `pfdk_retries` attempts in total (default 3, minimum 1), with a pause of 3 seconds and a message that does not contain credentials. Login errors and missing files SHALL NOT be retried. A download attempt SHALL start with an empty file.

#### Scenario: timeout, then success
- **WHEN** the first attempt times out and the second succeeds
- **THEN** the command succeeds and one retry message was shown

#### Scenario: all attempts fail
- **WHEN** every attempt times out
- **THEN** the command fails with "failed after N attempts" and the last error

#### Scenario: login denied
- **WHEN** the server rejects the login
- **THEN** there is no second attempt
