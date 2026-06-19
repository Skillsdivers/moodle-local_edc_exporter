# Plugin Structure

This plugin follows Moodle plugin conventions and keeps Moodle-discovered files in the plugin root when Moodle expects them there.

## Root

- `version.php`: plugin version metadata.
- `settings.php`: site administration settings page.
- `lib.php`: Moodle callback functions.
- `index.php`, `view.php`, `download.php`, `verify.php`, `revoke.php`, `regenerate.php`, `generate_pending.php`, `setup.php`, `fields.php`, `help.php`, `create_fields.php`: public or authenticated web entrypoints.
- `README.md`, `LICENSE`, `CHANGELOG.md`, `CONTRIBUTING.md`, `SECURITY.md`: repository and publication metadata.
- `styles.css`: Moodle plugin stylesheet.
- `thirdpartylibs.xml`: third-party asset metadata.

## Folders

- `classes/`: autoloaded Moodle classes using the `local_edc_exporter` namespace.
- `classes/local/credential/`: credential generation, JSON, validation, file and display services.
- `classes/local/setup/`: setup assistant and course metadata checks.
- `classes/local/audit/`: audit logging service.
- `classes/local/ui/`: shared output helpers.
- `classes/privacy/`: Moodle Privacy API provider.
- `classes/form/`: Moodle forms.
- `db/`: database schema, upgrades, capabilities, caches and events.
- `lang/en/`: English language strings for the official package.
- `cli/`: command-line scripts.
- `pix/`: static plugin images.
- `fonts/`: bundled fonts used for generated credential covers.
- `docs/`: maintainer documentation that is not loaded directly by Moodle.

## Rationale

Moving root web entrypoints into another folder would change plugin URLs such as `/local/edc_exporter/index.php` and `/local/edc_exporter/verify.php`. The current structure keeps those URLs stable while using namespaced classes for reusable code, as recommended by Moodle coding style and common plugin file conventions.
