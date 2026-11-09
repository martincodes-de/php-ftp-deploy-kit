## 1. Error handling

- [x] 1.1 Make `CurlRemoteFiles::download` return false only for missing/empty files and throw on other curl errors (tests with unreachable host, missing file, empty file)
- [x] 1.2 Let the fake throw on request and test backup, pull and push abort without side effects
- [x] 1.3 Update the `RemoteFiles` contract wording

## 2. Wrap-up

- [x] 2.1 Remove unnecessary comments from the touched code
- [x] 2.2 Run the full test suite and make it pass
