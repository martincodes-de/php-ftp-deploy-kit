## Why

On classic FTP hosts the web root is often the folder you upload into, so the whole project, including `.env.prod.local` with its secrets, `src/` and `var/`, would be downloadable. Symfony also needs the web server to send requests to `public/index.php`. hatago already ships a `.htaccess` for this by hand; the Symfony preset should do it out of the box.

## What Changes

- The `symfony` preset (and so `symfony-sqlite`) ships two editable templates, `htaccess.root` and `htaccess.public`, and uploads them as `.htaccess` and `public/.htaccess`.
- The root file sends every request into `public/`, so nothing outside `public/` is reachable. The public file routes requests to `index.php`.
- Both targets are excluded from the sync, so dg neither overwrites nor deletes them.
- The README says this is for Apache; other web servers need their own configuration.

## Capabilities

### New Capabilities
- `htaccess-files`: the access rules the Symfony preset deploys.

### Modified Capabilities
None.

## Impact

Preset folder `presets/symfony`, README. If the host's web root is already `public/`, the root file is simply not used.
