## 1. Connection type

- [x] 1.1 Map `ftps://` to explicit TLS and drop forced TLS for `ftp://` in `CurlRemoteFiles` (tests on the resulting URL)
- [x] 1.2 Warn about plain FTP in `backup`, `db:pull` and `db:push` (tests for ftp, ftps and file)
- [x] 1.3 Use `ftps://` in the preset example and document the difference in the README

## 2. Wrap-up

- [x] 2.1 Remove unnecessary comments from the touched code
- [x] 2.2 Run the full test suite and make it pass
