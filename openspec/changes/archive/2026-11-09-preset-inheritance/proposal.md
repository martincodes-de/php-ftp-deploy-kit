## Why

`symfony-sqlite` is the `symfony` preset plus a few SQLite-specific lines (database settings, a backup before the build). Keeping two full copies means every fix to the shared Symfony steps has to be made twice.

## What Changes

- A preset can extend another preset with a file named `extends`. `pfdk init` copies the base preset's files first, then the extending preset's own files.
- The base preset marks extension points with comment lines such as `; @pfdk:settings`. The extending preset provides `<marker>.snippet` files whose content replaces the matching marker line. Snippet files are never copied into the project; unused markers are removed.
- `symfony-sqlite` becomes `symfony` plus two snippets.

## Capabilities

### New Capabilities
- `preset-inheritance`: how presets extend each other.

### Modified Capabilities
None.

## Impact

`InitCommand`, the `symfony` and `symfony-sqlite` preset folders, README. The files produced by `pfdk init symfony-sqlite` stay the same, apart from the shared Symfony improvements.
