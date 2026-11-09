## Why

The `@public@` placeholder, the `--public` option and the interactive question made `pfdk init` more complicated than the use case needs: the web root is `public` in practically every Symfony project, and the files are plain text that can be edited. The two `.htaccess` files already cover both hosting situations (web root `public/`, or the whole folder as web root), so no switch is needed either.

## What Changes

- **BREAKING (unreleased)** Remove `--public`, the `@public@` placeholder and the question for the folder. The presets contain `public` literally.
- The README explains the two hosting cases and what to edit if the folder has another name.

## Capabilities

### New Capabilities
None.

### Modified Capabilities
- `htaccess-files`: the public folder is no longer configured by `init`.

## Impact

`InitCommand`, `presets/symfony`, tests, README.
