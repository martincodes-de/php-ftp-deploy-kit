## Why

pfdk ships presets for Symfony only, because that is what its author needs. Others should be able to add Laravel, WordPress or their own setups, either as a pull request or privately in their own project, without changing pfdk's code.

## What Changes

- `pfdk init <path>` accepts a path to a preset folder. A custom preset may `extends` a bundled preset by name or another folder by path.
- `CONTRIBUTING.md` and the README describe how a preset is built (files, `extends`, markers, snippets) and what a pull request needs (a folder under `presets/`; the existing tests check that every preset parses).

## Capabilities

### New Capabilities
- `custom-presets`: installing presets from a folder path.

### Modified Capabilities
None.

## Impact

`InitCommand` (already resolves folders), tests, `CONTRIBUTING.md`, README.
