# Contributing

Contributions are welcome, especially presets for other kinds of projects (Laravel, WordPress, ...).

## Adding a preset

A preset is a folder under `presets/<name>/` (lowercase letters, digits and dashes). Everything in it is copied into the folder where `pfdk init` runs. Minimum:

```
presets/wordpress/
  deploy.ini.example     # must contain a remote such as ftps://USER:PASSWORD@HOST/PATH
```

Keep the preset to what dg/ftp-deployment's `before[]`/`after[]`/`ignore` can do. In commands, call pfdk as `php ftp-deployment/vendor/bin/pfdk ...` (`init` renames `ftp-deployment` to the real folder) and use `pfdk exec -c ftp-deployment/deploy.ini -- <command>` for local build steps, so they work with and without DDEV.

### Building on another preset

Add a file `extends` containing the base preset's name (or a path to a folder). The base marks places where presets may add lines with a comment `; @pfdk:<marker>`; your preset provides `<marker>.snippet` files whose content replaces that line. Snippets are never copied, and unused markers are removed. See `presets/symfony-sqlite` (it extends `presets/symfony`).

### Placeholders and optional parts

`@public@` in any preset file becomes the public folder (default `public`, `pfdk init --public=web`). Parts that not everyone needs go between `; @pfdk:optional-start <feature>` and `; @pfdk:optional-end <feature>` lines, with their own files named `<feature>.*`; `pfdk init --without=<feature>` leaves all of it out (see `htaccess` in `presets/symfony`). The marker lines may also sit inside a quoted `ignore = "..."` value, because `init` removes them.

## Making a change

The project is developed spec-driven with [OpenSpec](https://github.com/Fission-AI/OpenSpec): propose a change (`openspec/changes/`), implement it with tests, and archive it. Conventions are in `openspec/config.yaml` and `CLAUDE.md`:

- Code, comments and docs in English. Comment only the "why", rarely.
- Keep it thin: pfdk does not reimplement dg/ftp-deployment, and every new feature must serve "set up a deployment in minutes" or protect data.
- Tests ship with the change. The suite installs and parses every bundled preset, so a broken preset fails the build. Run it with `ddev exec vendor/bin/phpunit`; it must pass before a change is archived.
