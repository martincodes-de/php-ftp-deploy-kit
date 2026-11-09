## REMOVED Requirements

### Requirement: The public folder is part of the installed files
**Reason**: Over-engineered. The folder is `public` in practically every Symfony project and the installed files are plain text that can be edited.
**Migration**: Replace `public` in the installed `deploy.ini`, `htaccess.root` and `htaccess.public` if your folder has another name.

## ADDED Requirements

### Requirement: Both hosting situations are covered
The `symfony` preset SHALL upload both a `public/.htaccess` (for a web root that points to `public/`) and a root `.htaccess` (for hosting where the whole folder is the web root), and the README SHALL explain which one does the work in which case.

#### Scenario: both are shipped
- **WHEN** `pfdk init symfony` runs
- **THEN** `htaccess.public` and `htaccess.root` exist and the ini uploads them to `public/.htaccess` and `.htaccess`
