## 1. Project directory

- [x] 1.1 Read the `local` key in `DeployConfig` and derive `projectDir()` from it; resolve `localDbFile()` and relative `backupFile()` against it (with tests for subfolder, default, absolute)
- [x] 1.2 Make `DeployCommand` look for the project's own dg binary relative to the project directory (test included)

## 2. Preset and docs

- [x] 2.1 Change the `symfony-sqlite` preset: ini lives in `ftp-deployment/`, `local = ..`, backup runs before `composer install --no-dev` (test that the order holds)
- [x] 2.2 Document the isolated install (`ftp-deployment/composer.json`) and the resulting paths in the README

## 3. Wrap-up

- [x] 3.1 Remove unnecessary comments from the touched code
- [x] 3.2 Run the full test suite and make it pass
- [x] 3.3 Run a code review at medium level and resolve its findings
