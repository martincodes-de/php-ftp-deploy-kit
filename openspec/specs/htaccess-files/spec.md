# htaccess-files Specification

## Purpose
TBD - created by archiving change htaccess-files. Update Purpose after archive.

## Requirements

### Requirement: Nothing outside public is served
The `symfony` preset SHALL ship a root `.htaccess` template that rewrites every request into `public/`, and SHALL upload it as `.htaccess` in the remote root.

#### Scenario: secrets are not reachable
- **WHEN** the project is uploaded into the web root with the preset
- **THEN** a request for `/.env.prod.local`, `/src/...` or `/var/...` is mapped to a path inside `public/` and therefore does not expose project files

### Requirement: Front controller routing
The preset SHALL ship a `public/.htaccess` template that sends requests for non-existing files to `index.php`, and SHALL upload it as `public/.htaccess`.

#### Scenario: pretty URL
- **WHEN** a request does not match an existing file
- **THEN** the rewrite rules hand it to `index.php`

### Requirement: The files are managed by the preset
Both remote files SHALL be excluded from the sync (`ignore`), and the templates SHALL be plain files in the project that the user can edit.

#### Scenario: not deleted by allowDelete
- **WHEN** `allowDelete = yes` and the local project has no `.htaccess`
- **THEN** the uploaded remote `.htaccess` is not deleted

### Requirement: Apache only is documented
The README SHALL state that the templates are for Apache.

#### Scenario: documentation
- **WHEN** the README is read
- **THEN** it says other web servers need their own rules

### Requirement: Both hosting situations are covered
The `symfony` preset SHALL upload both a `public/.htaccess` (for a web root that points to `public/`) and a root `.htaccess` (for hosting where the whole folder is the web root), and the README SHALL explain which one does the work in which case.

#### Scenario: both are shipped
- **WHEN** `pfdk init symfony` runs
- **THEN** `htaccess.public` and `htaccess.root` exist and the ini uploads them to `public/.htaccess` and `.htaccess`
