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

### Requirement: The public folder is part of the installed files
`pfdk init` SHALL replace the placeholder `@public@` in the preset files with the public folder, so the generated files contain the folder name as plain text. The folder SHALL default to `public`, MAY be given with `--public` or, in an interactive terminal, answered when the preset uses the placeholder, and SHALL be a relative path without `..`.

#### Scenario: default
- **WHEN** `pfdk init symfony` runs without `--public`
- **THEN** the installed files refer to `public`

#### Scenario: custom folder
- **WHEN** `pfdk init symfony --public=web` runs
- **THEN** the ignore entry, the upload job, the root rewrite rule and the asset cleanup refer to `web`

#### Scenario: unsafe folder
- **WHEN** `--public=../outside` is given
- **THEN** the command fails and installs nothing

### Requirement: Apache only is documented
The README SHALL state that the templates are for Apache.

#### Scenario: documentation
- **WHEN** the README is read
- **THEN** it says other web servers need their own rules
