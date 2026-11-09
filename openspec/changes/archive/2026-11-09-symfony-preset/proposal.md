## Why

Many Symfony apps have no database. The only Symfony preset, `symfony-sqlite`, has a backup step and database settings that fail or make no sense without one. Such apps usually use AssetMapper, whose compiled assets must be built before the upload and removed locally afterwards.

## What Changes

- New preset `symfony`: no database keys and no backup step; production `composer install`, AssetMapper compile, prod flag file, cache purge, restore of dev dependencies and removal of the compiled assets.

## Capabilities

### New Capabilities
- `symfony-preset`: what the database-free Symfony preset contains.

### Modified Capabilities
None.

## Impact

New folder `presets/symfony`, README preset list. Existing presets are unchanged.
