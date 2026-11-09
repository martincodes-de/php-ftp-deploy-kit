## 1. Requirements

- [x] 1.1 Add a pure `Requirements` class mapping scheme, curl capabilities and loaded extensions to a list of problems (tests for every scheme and the deploy extras)
- [x] 1.2 Guard `backup`, `db:pull`, `db:push` and `deploy` with it (tests that a failing guard stops before any transfer)
- [x] 1.3 Add `pfdk check` (tests: working config, no config; broken configs through the pure class)
- [x] 1.4 Suggest `ext-ssh2` in `composer.json`, document `pfdk check` in the README

## 2. Wrap-up

- [x] 2.1 Remove unnecessary comments from the touched code
- [x] 2.2 Run the full test suite and make it pass
