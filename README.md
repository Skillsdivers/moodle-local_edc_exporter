# EDC Exporter

## Description

EDC Exporter is a Moodle local plugin that allows authorised users to generate, view, download, revoke and verify Moodle course completion credentials as European Digital Credential-compatible JSON.

The plugin is designed for institutional Moodle sites that need a guided and auditable credential workflow: issuer configuration, required course metadata checks, generated credential records, visual credential covers, public verification tokens, revocation, audit logging and Moodle Privacy API support.

## Important note

This plugin does not issue official Europass credentials by itself. It exports credential data in a format designed to support European Digital Credential workflows.

## Features

- Credential generation from Moodle course completion data
- JSON export
- Visual credential view
- Issuer logo and configuration
- Verification URL and token
- Revocation
- Audit logging
- Privacy API support

## Requirements

- Moodle 4.5 LTS or later
- PHP version supported by the target Moodle release
- PHP extensions required by Moodle core
- PHP `json` extension for JSON generation and validation
- PHP `gd` extension for generated visual cover images
- Moodle cron is recommended so course completion events are processed normally

## Installation

1. Copy the `edc_exporter` directory to `local/edc_exporter`.
2. Visit `Site administration > Notifications`.
3. Run the Moodle upgrade process.
4. Configure the plugin settings.

## Configuration

- Issuer name
- Issuer URL
- Issuer logo
- Awarding body country and legal details
- Visual template settings
- Required course custom fields for learning outcomes and workload
- Optional course custom fields for EQF level, modality, assessment type and descriptions

## Usage

1. Complete the plugin configuration.
2. Create or confirm the recommended course custom fields.
3. Complete the required metadata in each course.
4. Complete a course as a learner.
5. Generate pending credentials from the course credential dashboard.
6. View the visual credential summary.
7. Download the European Digital Credential-compatible JSON.
8. Open the public verification URL.
9. Revoke credentials when required.

## Capabilities

| Capability | Default roles | Purpose |
|---|---|---|
| `local/edc_exporter:view` | Manager, course creator, editing teacher, teacher | View course credentials and credential summaries. |
| `local/edc_exporter:download` | Manager | Download export JSON. |
| `local/edc_exporter:downloadinternal` | Manager | Download internal Moodle traceability JSON. |
| `local/edc_exporter:generatepending` | Manager | Generate pending credentials for completed learners. |
| `local/edc_exporter:regenerate` | Manager | Regenerate failed credentials. |
| `local/edc_exporter:revoke` | Manager | Revoke issued credentials. |

## Privacy

The plugin stores credential lifecycle records linked to Moodle users and courses. Stored data can include:

- user id
- course id
- course completion id
- credential status
- issue, expiration and revocation data
- generated JSON and relative file paths
- public verification token
- payload hash
- validation and generation error summaries
- audit logs containing credential id, user id, course id, actor id, action, minimal details and timestamp

Generated `export_json` and `internal_json` may include personal data such as learner name and email. Internal JSON is restricted by a separate capability. The plugin implements Moodle Privacy API metadata, export and deletion methods.

## Public verification

The public verification page is intentionally available without login when a valid random token is provided. It shows only minimal credential status data:

- learner name
- course name
- issuer name
- issue date
- status
- expiration date, when configured
- revocation date, when revoked

It does not show email addresses, full JSON payloads, validation errors, technical error messages or internal Moodle ids.

## Revocation

Authorised users can revoke generated credentials. Revocation updates the credential status, revocation time, revoking user, revocation reason, modifying user and modification time. Revoked credentials are shown as revoked by the verification page and cannot be downloaded as valid export JSON.

## Audit Logging

The plugin stores minimal internal audit records for important actions: issued, regenerated, downloaded, viewed, revoked, verified and error. There is no public audit page in this release; audit data is stored internally and covered by the Privacy API.

## Limitations

- Not an official Europass issuer by itself.
- Compatibility depends on downstream European Digital Credential validation and viewer requirements.
- Administrator configuration is required before production use.
- The bundled validator performs internal structural checks and does not replace downstream EDC validation.

## Support

Use the issue tracker of the source repository. Recommended repository name: `moodle-local_edc_exporter`.

## Additional Documentation

- Manual testing checklist: `docs/manual-test.md`
- Moodle Plugins Directory submission text: `docs/marketplace-submission.md`
- Plugin structure notes: `docs/structure.md`

## License

GPL-3.0-or-later.
