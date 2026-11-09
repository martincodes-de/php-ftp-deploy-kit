## Why

When an ini has several sections, or a project has several deploy inis, pfdk currently stops with an error. In a terminal it can simply ask. Running from the project root also forces `-c ftp-deployment/deploy.ini` on every call although there is usually exactly one ini.

## What Changes

- When `--config` is not given, pfdk uses `deploy.ini` in the current folder, else the only `deploy*.ini` / `*/deploy*.ini` it finds; with several candidates it asks (interactive) or fails listing them.
- When an ini has sections and `--section` is not given, pfdk asks which one to use (interactive) and otherwise keeps failing with the list.
- `pfdk deploy` on a project with `pfdk_type = symfony` and a configured database asks whether to pull, migrate and push the database first (interactive only, default no).

## Capabilities

### New Capabilities
- `interactive-choice`: finding the ini and asking where a choice is needed.

### Modified Capabilities
None.

## Impact

`BaseCommand::config`, `DeployConfig` (a dedicated exception for the missing section), `DeployCommand`, README. Non-interactive runs behave as before.
