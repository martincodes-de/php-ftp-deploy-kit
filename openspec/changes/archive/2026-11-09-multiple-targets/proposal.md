## Why

One project often has several deploy targets (staging and production, or several hosts). dg/ftp-deployment supports that with ini sections and `--section`. pfdk reads the ini flat, so with sections the last section silently wins, and `db:push` could hit production while the user meant staging. Separate ini files per target work today but are not documented.

## What Changes

- A `--section <name>` option on every command that reads the ini. Sections work like in dg: an ini without a top-level `remote` consists of named sections; top-level `pfdk_*` keys act as defaults that a section may override.
- An ini with sections and no `--section` is refused with the list of sections, never guessed.
- `deploy` passes `--section` on to dg/ftp-deployment.
- README documents both ways to have several targets: sections and one ini per target.

## Capabilities

### New Capabilities
- `multiple-targets`: selecting a deploy target from an ini with sections.

### Modified Capabilities
None.

## Impact

`DeployConfig::load`, `BaseCommand`, `DeployCommand`, `MigrateCommand`, README. Single-target inis behave exactly as before.
