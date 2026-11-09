## Why

Setting up a deployment should take seconds. Today the user must create a folder, run `composer init`, copy the example to `deploy.ini` and edit `.gitignore` by hand.

## What Changes

- `pfdk init <preset>` also creates `deploy.ini` from the example (if it does not exist) and adds the pfdk lines to the project's `.gitignore` (once, between markers) when the folder sits inside a git project.
- `pfdk init` without a preset in an interactive terminal asks which preset to use instead of only listing them.
- The README install steps shrink to: create folder, `composer require`, `pfdk init`.

## Capabilities

### New Capabilities
- `quick-setup`: what `pfdk init` sets up besides the preset files.

### Modified Capabilities
None.

## Impact

`InitCommand`, README.
