## Why

The presets mention the folder `ftp-deployment/` in several paths, but that folder name is only a convention. Whoever picks another name would have to rewrite those paths by hand. The Symfony preset also uploads `.phpunit.result.cache`.

## What Changes

- `pfdk init` replaces `ftp-deployment` in the copied files with the name of the folder it runs in.
- The `symfony-sqlite` preset ignores `.phpunit.result.cache` and shows `pfdk_type = symfony` as a commented-out opt-in.

## Capabilities

### New Capabilities
- `preset-init`: how presets are copied into a project.

### Modified Capabilities
None.

## Impact

`InitCommand`, the `symfony-sqlite` preset, README.
