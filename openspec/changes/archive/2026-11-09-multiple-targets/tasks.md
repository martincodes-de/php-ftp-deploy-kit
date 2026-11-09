## 1. Sections

- [x] 1.1 Parse sections in `DeployConfig::load` and merge a selected section over the top-level keys (tests: override, single-target ini, list keys are not sections, refuse without section, unknown section, section on single-target ini)
- [x] 1.2 Add `--section` to `BaseCommand`, forward it from `deploy` to dg and from `db:migrate` to its sub-commands (tests)
- [x] 1.3 Document sections and one-ini-per-target in the README

## 2. Wrap-up

- [x] 2.1 Remove unnecessary comments from the touched code
- [x] 2.2 Run the full test suite and make it pass
