# PHP FTP Deploy Kit (`pfdk`)

## Goal

The owner deploys small PHP apps (mostly Symfony, often with SQLite) to FTP-only shared hosting.
Each app (activity-match, hatago, digital-birthday-invite, ...) had its own copy-pasted
Makefile + `ftp-deployment/` folder. This package replaces those copies: one Composer package,
one `deploy.ini` per app, the same commands everywhere.

Why this exists: the owner likes Nette's FTP deployment tool (dg/ftp-deployment), which is what these apps
already use. Over time the same standard use cases kept coming back (back up, pull and push the database,
run migrations around a deploy), so the idea was to build a small package on top of it instead of copying
Makefiles around. Origin of the implementation: activity-match's `Makefile` and `docs/deploy.md`.

The project is open for reuse, forks and changes.

## Decisions (do not re-litigate without asking)

- `dg/ftp-deployment` does the actual deployment. pfdk wraps it, never reimplements it.
- `deploy.ini` is the only config file. pfdk's own keys are prefixed `pfdk_` (dg ignores unknown keys). No YAML.
- No maintenance mode. Users add such steps themselves as `before[]`/`after[]` jobs in the ini, because
  whether an app has one is the app's decision.
- Steps (build, cache, ...) live in the ini, not in pfdk.
- Presets are plain folders in `presets/<name>/`, copied by `pfdk init`; the project owns the files afterwards.
- Database support is file based (SQLite). It must stay optional: no `pfdk_db` means no database commands, deploy still works.
- Minimal abstraction, easy to understand. Each Console command is the command: one class, one `execute()`,
  logic written out. No use-case layer, buses or handlers. The only test seam is `RemoteFiles` (FTP).
- Everything in English: code, comments, docs, READMEs, specs.
- Comments: do not comment by default. Remove unnecessary ones (anything that restates what the code does or
  the signature). Where a comment is needed, keep it as short as possible and explain why, never what.
  Apply this to every new change and to code you touch.
- Names: package `martincodes-de/php-ftp-deploy-kit`, binary `pfdk`.

## Workflow: spec-driven development first

This project is developed spec-driven first: every change starts as an OpenSpec change in `openspec/`
(`/opsx:propose`, `/opsx:apply`, `/opsx:archive`), and code follows the spec, not the other way round.
Rules live in `openspec/config.yaml`.

Before a change is archived, in this order:

1. **Run the full test suite; it must pass.** Never archive on red or unrun tests.
2. **Run a code review at medium level** (`/code-review medium`) and fix or consciously dismiss every finding.
   This is how we make sure a new spec/change is actually good, not just green.

The point: the package is the safety net for production deployments and database overwrites, so a regression
here can destroy a live site's data.

- Tests ship with the behavior they cover, in the same task.
- Keep coverage high and meaningful (currently ~99 % of lines): happy path plus the failure paths that matter
  (missing remote file, empty download, aborted confirmation, invalid config).

## Development

DDEV without a database container; the web container only exists because DDEV requires one.

```bash
ddev start
ddev composer install
ddev exec vendor/bin/phpunit
ddev xdebug on && ddev exec "XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-text"
```

OpenSpec needs Node >= 22.12: `npx -y -p node@22 -p @fission-ai/openspec openspec <command>`.

## Testing notes

- Tests use real temp directories (`ProjectTestCase` also changes the working directory) and Symfony's `CommandTester`.
- FTP is faked with `InMemoryRemoteFiles` for the commands; `CurlRemoteFiles` itself is tested against `file://localhost/...`,
  which curl handles like a server.
- Not testable hermetically: `DeployCommand` finding no dg tool at all (the package's own vendor copy always exists in dev).
