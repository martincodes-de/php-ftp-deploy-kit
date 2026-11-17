## 1. The switch

- [x] 1.1 `deploy --no-db`: no migration offer, `PFDK_NO_DB=1` for dg, one line of output; tests (question, variable visible in jobs, without the option it is unset)
- [x] 1.2 `BaseCommand::databaseDisabled()`; `backup` skips, `db:pull`, `db:push`, `db:init`, `db:migrate` refuse; tests for each
- [x] 1.3 README: option, variable, example
- [x] 1.4 Remove unnecessary comments from the touched code

## 2. Wrap-up

- [x] 2.1 Run the full test suite and make it pass
