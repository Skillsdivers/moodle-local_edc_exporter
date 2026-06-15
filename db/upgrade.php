<?php
// This file is part of Moodle - https://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Upgrade steps for the EDC exporter plugin.
 *
 * @package    local_edc_exporter
 * @copyright  2026 Skills Divers
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Performs database upgrades for the EDC exporter plugin.
 *
 * @param int $oldversion Previously installed plugin version.
 * @return bool True when the upgrade completed.
 */
function xmldb_local_edc_exporter_upgrade($oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026042701) {
        // Tracking table for one export lifecycle per user/course pair.
        $table = new xmldb_table('local_edcexport_cred');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('completionid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('credentialuuid', XMLDB_TYPE_CHAR, '36', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
        $table->add_field('timecompleted', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('gradejson', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('jsonpath', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('payloadhash', XMLDB_TYPE_CHAR, '64', null, null, null, null);
        $table->add_field('validationerrors', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('errormessage', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('courseid_fk', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);

        $table->add_index('usercourse_uix', XMLDB_INDEX_UNIQUE, ['userid', 'courseid']);
        $table->add_index('uuid_uix', XMLDB_INDEX_UNIQUE, ['credentialuuid']);
        $table->add_index('status_ix', XMLDB_INDEX_NOTUNIQUE, ['status']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Moodle savepoint so future upgrades know this step has already run.
        upgrade_plugin_savepoint(true, 2026042701, 'local', 'edc_exporter');
    }

    if ($oldversion < 2026042800) {
        $table = new xmldb_table('local_edcexport_cred');

        $oldfield = new xmldb_field('credentialuuid', XMLDB_TYPE_CHAR, '36', null, XMLDB_NOTNULL, null, null);
        $newfield = new xmldb_field('credentialid', XMLDB_TYPE_CHAR, '36', null, XMLDB_NOTNULL, null, null, 'completionid');

        if ($dbman->field_exists($table, $oldfield) && !$dbman->field_exists($table, $newfield)) {
            $dbman->rename_field($table, $oldfield, 'credentialid');
        }

        $newfields = [
            new xmldb_field('internal_json', XMLDB_TYPE_TEXT, null, null, null, null, null, 'status'),
            new xmldb_field('export_json', XMLDB_TYPE_TEXT, null, null, null, null, null, 'internal_json'),
            new xmldb_field('internal_json_path', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'export_json'),
            new xmldb_field('export_json_path', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'internal_json_path'),
        ];

        foreach ($newfields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        $deprecatedfields = [
            new xmldb_field('timecompleted'),
            new xmldb_field('gradejson'),
            new xmldb_field('jsonpath'),
        ];

        foreach ($deprecatedfields as $field) {
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }
        }

        $olduuidindex = new xmldb_index('uuid_uix', XMLDB_INDEX_UNIQUE, ['credentialid']);
        if ($dbman->index_exists($table, $olduuidindex)) {
            $dbman->drop_index($table, $olduuidindex);
        }

        $newuuidindex = new xmldb_index('credentialid_uix', XMLDB_INDEX_UNIQUE, ['credentialid']);
        if (!$dbman->index_exists($table, $newuuidindex)) {
            $dbman->add_index($table, $newuuidindex);
        }

        upgrade_plugin_savepoint(true, 2026042800, 'local', 'edc_exporter');
    }

    if ($oldversion < 2026060201) {
        $table = new xmldb_table('local_edcexport_cred');

        $newfields = [
            new xmldb_field('issuedat', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'export_json_path'),
            new xmldb_field('expiresat', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'issuedat'),
            new xmldb_field('revokedat', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'expiresat'),
            new xmldb_field('revokedby', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'revokedat'),
            new xmldb_field('revocationreason', XMLDB_TYPE_TEXT, null, null, null, null, null, 'revokedby'),
            new xmldb_field('verificationtoken', XMLDB_TYPE_CHAR, '64', null, null, null, null, 'revocationreason'),
            new xmldb_field('version', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1', 'verificationtoken'),
            new xmldb_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'version'),
        ];

        foreach ($newfields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Backfills minimum data for existing credentials.
        $records = $DB->get_records('local_edcexport_cred');

        foreach ($records as $record) {
            $changed = false;

            if (empty($record->issuedat) && $record->status === 'generated') {
                $record->issuedat = !empty($record->timemodified) ? $record->timemodified : time();
                $changed = true;
            }

            if (empty($record->verificationtoken)) {
                // Secure 64-character hexadecimal token for public verification.
                do {
                    $record->verificationtoken = bin2hex(random_bytes(32));
                } while (
                    $DB->record_exists('local_edcexport_cred', [
                        'verificationtoken' => $record->verificationtoken,
                    ])
                );

                $changed = true;
            }

            if (empty($record->version)) {
                $record->version = 1;
                $changed = true;
            }

            if ($changed) {
                $DB->update_record('local_edcexport_cred', $record);
            }
        }

        $indexes = [
            new xmldb_index('verificationtoken_uix', XMLDB_INDEX_UNIQUE, ['verificationtoken']),
            new xmldb_index('userid_ix', XMLDB_INDEX_NOTUNIQUE, ['userid']),
            new xmldb_index('courseid_ix', XMLDB_INDEX_NOTUNIQUE, ['courseid']),
        ];

        foreach ($indexes as $index) {
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }

        $revokedbykey = new xmldb_key('revokedby_fk', XMLDB_KEY_FOREIGN, ['revokedby'], 'user', ['id']);

        // Moodle has no key_exists(). find_key_name() checks whether an equivalent key already exists.
        if (!$dbman->find_key_name($table, $revokedbykey)) {
            $dbman->add_key($table, $revokedbykey);
        }

        $usermodifiedkey = new xmldb_key('usermodified_fk', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);

        // Avoids attempting to create the same foreign key twice when rerunning the upgrade.
        if (!$dbman->find_key_name($table, $usermodifiedkey)) {
            $dbman->add_key($table, $usermodifiedkey);
        }

        upgrade_plugin_savepoint(true, 2026060201, 'local', 'edc_exporter');
    }

    if ($oldversion < 2026060211) {
        // Minimal audit table for important credential actions.
        $table = new xmldb_table('local_edcexport_log');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);

        // Internal local_edcexport_cred.id.
        $table->add_field('credentialid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Credential holder user.
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Course linked to the credential.
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // User performing the action. It can be null for public verification.
        $table->add_field('actorid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Recorded action: issued, regenerated, downloaded, viewed, revoked, verified, or error.
        $table->add_field('action', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null);

        // Minimum details. Do not store full JSON or unnecessary personal data.
        $table->add_field('details', XMLDB_TYPE_TEXT, null, null, null, null, null);

        // Unix timestamp for the audit event.
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('credentialid_fk', XMLDB_KEY_FOREIGN, ['credentialid'], 'local_edcexport_cred', ['id']);
        $table->add_key('userid_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('courseid_fk', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_key('actorid_fk', XMLDB_KEY_FOREIGN, ['actorid'], 'user', ['id']);

        $table->add_index('action_ix', XMLDB_INDEX_NOTUNIQUE, ['action']);
        $table->add_index('timecreated_ix', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026060211, 'local', 'edc_exporter');
    }

    return true;
}
