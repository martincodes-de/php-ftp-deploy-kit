## Why

Not everyone needs the `.htaccess` part of the Symfony presets (the host may serve `public/` already, or may not run Apache). Deleting four scattered lines and two files by hand is easy to get wrong.

## What Changes

- Presets can mark optional parts with `; @pfdk:optional-start <feature>` ... `; @pfdk:optional-end <feature>` lines.
- `pfdk init <preset> --without=<feature>` (repeatable) leaves out those blocks and every preset file named `<feature>.*`.
- The Symfony presets mark their `.htaccess` ignore entries and upload jobs as the optional feature `htaccess`.

## Capabilities

### New Capabilities
- `optional-features`: opting out of marked parts of a preset.

### Modified Capabilities
None.

## Impact

`InitCommand`, `presets/symfony`, README, CONTRIBUTING.
