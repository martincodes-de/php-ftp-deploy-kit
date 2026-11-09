## 1. DDEV detection

- [x] 1.1 Add `pfdk_ddev` to `DeployConfig` and a `Ddev` helper that detects a running project (tests with a fake `ddev` on PATH: running, stopped, no config file, binary missing, disabled)

## 2. Commands

- [x] 2.1 Use DDEV in `db:migrate` for the default command (tests: DDEV active, explicit command wins)
- [x] 2.2 Add `pfdk exec` (tests: host run, DDEV run, exit status passthrough)
- [x] 2.3 Switch the `symfony-sqlite` preset to `pfdk exec` (test) and document DDEV support in the README

## 3. Wrap-up

- [x] 3.1 Remove unnecessary comments from the touched code
- [x] 3.2 Run the full test suite and make it pass
