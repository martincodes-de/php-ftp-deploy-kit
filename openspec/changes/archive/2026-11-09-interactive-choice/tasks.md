## 1. Ini discovery

- [x] 1.1 Discover the ini when `--config` is absent and ask or fail when ambiguous (tests: current folder, one in a subfolder, several interactive/non-interactive, explicit wins)

## 2. Section choice

- [x] 2.1 Throw a dedicated exception from `DeployConfig` when a section is required and ask in `BaseCommand` when interactive (tests)

## 3. Migration question

- [x] 3.1 Ask in `DeployCommand` before deploying a Symfony project with a database; stop when the migration fails (tests)

## 4. Wrap-up

- [x] 4.1 Remove unnecessary comments from the touched code
- [x] 4.2 Run the full test suite and make it pass
