# Contributing

Thank you for considering a contribution to EDC Exporter.

## Before large changes

Open an issue before starting large feature work, schema changes or changes that affect credential JSON semantics.

## Coding style

- Follow Moodle coding style.
- Keep user-facing text in `lang/en/local_edc_exporter.php`.
- Use Moodle APIs for database access, files, output, parameters and permissions.
- Keep changes conservative and covered by manual testing.

## Local checks

Run these checks before submitting changes:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

Run Moodle CodeChecker against the plugin when available.
