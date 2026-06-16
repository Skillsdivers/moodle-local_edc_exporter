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

namespace local_edc_exporter\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy API implementation for local_edc_exporter.
 *
 * The plugin stores credential records linked to Moodle users and courses.
 * Some JSON fields can contain personal data such as learner name and email.
 *
 * @package    local_edc_exporter
 * @copyright  2026 Skills Divers
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describes the personal data stored by this plugin.
     *
     * @param collection $collection Metadata collection.
     * @return collection Updated metadata collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_edc_exporter_cred',
            [
                'userid' => 'privacy:metadata:local_edc_exporter_cred:userid',
                'courseid' => 'privacy:metadata:local_edc_exporter_cred:courseid',
                'completionid' => 'privacy:metadata:local_edc_exporter_cred:completionid',
                'credentialid' => 'privacy:metadata:local_edc_exporter_cred:credentialid',
                'status' => 'privacy:metadata:local_edc_exporter_cred:status',
                'internal_json' => 'privacy:metadata:local_edc_exporter_cred:internal_json',
                'export_json' => 'privacy:metadata:local_edc_exporter_cred:export_json',
                'internal_json_path' => 'privacy:metadata:local_edc_exporter_cred:internal_json_path',
                'export_json_path' => 'privacy:metadata:local_edc_exporter_cred:export_json_path',
                'issuedat' => 'privacy:metadata:local_edc_exporter_cred:issuedat',
                'expiresat' => 'privacy:metadata:local_edc_exporter_cred:expiresat',
                'revokedat' => 'privacy:metadata:local_edc_exporter_cred:revokedat',
                'revokedby' => 'privacy:metadata:local_edc_exporter_cred:revokedby',
                'revocationreason' => 'privacy:metadata:local_edc_exporter_cred:revocationreason',
                'verificationtoken' => 'privacy:metadata:local_edc_exporter_cred:verificationtoken',
                'version' => 'privacy:metadata:local_edc_exporter_cred:version',
                'payloadhash' => 'privacy:metadata:local_edc_exporter_cred:payloadhash',
                'validationerrors' => 'privacy:metadata:local_edc_exporter_cred:validationerrors',
                'errormessage' => 'privacy:metadata:local_edc_exporter_cred:errormessage',
                'timecreated' => 'privacy:metadata:local_edc_exporter_cred:timecreated',
                'timemodified' => 'privacy:metadata:local_edc_exporter_cred:timemodified',
                'usermodified' => 'privacy:metadata:local_edc_exporter_cred:usermodified',
            ],
            'privacy:metadata:local_edc_exporter_cred'
        );

        $collection->add_database_table(
            'local_edc_exporter_log',
            [
                'credentialid' => 'privacy:metadata:local_edc_exporter_log:credentialid',
                'userid' => 'privacy:metadata:local_edc_exporter_log:userid',
                'courseid' => 'privacy:metadata:local_edc_exporter_log:courseid',
                'actorid' => 'privacy:metadata:local_edc_exporter_log:actorid',
                'action' => 'privacy:metadata:local_edc_exporter_log:action',
                'details' => 'privacy:metadata:local_edc_exporter_log:details',
                'timecreated' => 'privacy:metadata:local_edc_exporter_log:timecreated',
            ],
            'privacy:metadata:local_edc_exporter_log'
        );

        return $collection;
    }

    /**
     * Returns course contexts where the user has EDC credential records.
     *
     * @param int $userid Moodle user ID.
     * @return contextlist Context list.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {local_edc_exporter_cred} cred
                    ON cred.courseid = ctx.instanceid
                 WHERE ctx.contextlevel = :contextlevel
                   AND cred.userid = :userid";

        $params = [
            'contextlevel' => CONTEXT_COURSE,
            'userid' => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {local_edc_exporter_log} log
                    ON log.courseid = ctx.instanceid
                 WHERE ctx.contextlevel = :contextlevel
                   AND (log.userid = :userid OR log.actorid = :actorid)";

        $params = [
            'contextlevel' => CONTEXT_COURSE,
            'userid' => $userid,
            'actorid' => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Adds users with credential records inside a course context.
     *
     * @param userlist $userlist User list supplied by Moodle privacy subsystem.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if (!$context instanceof \context_course) {
            return;
        }

        $sql = "SELECT userid
                  FROM {local_edc_exporter_cred}
                 WHERE courseid = :courseid";

        $params = [
            'courseid' => $context->instanceid,
        ];

        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "SELECT userid
                  FROM {local_edc_exporter_log}
                 WHERE courseid = :courseid
                   AND userid IS NOT NULL
                 UNION
                SELECT actorid
                  FROM {local_edc_exporter_log}
                 WHERE courseid = :actorcourseid
                   AND actorid IS NOT NULL";

        $params = [
            'courseid' => $context->instanceid,
            'actorcourseid' => $context->instanceid,
        ];

        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Exports credential data for the approved user and contexts.
     *
     * @param approved_contextlist $contextlist Approved context list.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_course) {
                continue;
            }

            $records = $DB->get_records(
                'local_edc_exporter_cred',
                [
                    'userid' => $userid,
                    'courseid' => $context->instanceid,
                ],
                'timecreated ASC'
            );

            foreach ($records as $record) {
                $data = self::prepare_record_for_export($record);

                writer::with_context($context)->export_data(
                    [
                        get_string('privacy:credentialspath', 'local_edc_exporter'),
                        $record->credentialid,
                    ],
                    $data
                );
            }

            $logs = $DB->get_records_select(
                'local_edc_exporter_log',
                'courseid = :courseid AND (userid = :userid OR actorid = :actorid)',
                [
                    'courseid' => $context->instanceid,
                    'userid' => $userid,
                    'actorid' => $userid,
                ],
                'timecreated ASC'
            );

            foreach ($logs as $log) {
                writer::with_context($context)->export_data(
                    [
                        get_string('privacy:auditpath', 'local_edc_exporter'),
                        (string) $log->id,
                    ],
                    self::prepare_log_for_export($log)
                );
            }
        }
    }

    /**
     * Deletes all credential records in a course context.
     *
     * @param \context $context Moodle context.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if (!$context instanceof \context_course) {
            return;
        }

        $records = $DB->get_records(
            'local_edc_exporter_cred',
            ['courseid' => $context->instanceid]
        );

        $DB->delete_records('local_edc_exporter_log', ['courseid' => $context->instanceid]);

        foreach ($records as $record) {
            self::delete_record_files($record);
            $DB->delete_records('local_edc_exporter_cred', ['id' => $record->id]);
        }
    }

    /**
     * Deletes credential records for one user in the approved contexts.
     *
     * @param approved_contextlist $contextlist Approved context list.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_course) {
                continue;
            }

            $records = $DB->get_records(
                'local_edc_exporter_cred',
                [
                    'userid' => $userid,
                    'courseid' => $context->instanceid,
                ]
            );

            self::delete_logs_for_records($records, [
                'courseid' => $context->instanceid,
                'userid' => $userid,
                'actorid' => $userid,
            ]);

            foreach ($records as $record) {
                self::delete_record_files($record);
                $DB->delete_records('local_edc_exporter_cred', ['id' => $record->id]);
            }
        }
    }

    /**
     * Deletes credential records for several approved users in one course context.
     *
     * @param approved_userlist $userlist Approved user list.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();

        if (!$context instanceof \context_course) {
            return;
        }

        $userids = $userlist->get_userids();

        if (empty($userids)) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'userid');
        $params['courseid'] = $context->instanceid;

        $records = $DB->get_records_select(
            'local_edc_exporter_cred',
            "courseid = :courseid AND userid {$insql}",
            $params
        );

        self::delete_logs_for_records($records, $params, "courseid = :courseid AND (userid {$insql} OR actorid {$insql})");

        foreach ($records as $record) {
            self::delete_record_files($record);
            $DB->delete_records('local_edc_exporter_cred', ['id' => $record->id]);
        }
    }

    /**
     * Prepares a credential record for privacy export.
     *
     * Dates are transformed to readable Moodle dates.
     *
     * @param \stdClass $record Credential record.
     * @return \stdClass Data prepared for export.
     */
    protected static function prepare_record_for_export(\stdClass $record): \stdClass {
        return (object) [
            'userid' => $record->userid,
            'courseid' => $record->courseid,
            'completionid' => $record->completionid,
            'credentialid' => $record->credentialid,
            'status' => $record->status,
            'internal_json' => $record->internal_json,
            'export_json' => $record->export_json,
            'internal_json_path' => $record->internal_json_path,
            'export_json_path' => $record->export_json_path,
            'issuedat' => !empty($record->issuedat) ? transform::datetime($record->issuedat) : null,
            'expiresat' => !empty($record->expiresat) ? transform::datetime($record->expiresat) : null,
            'revokedat' => !empty($record->revokedat) ? transform::datetime($record->revokedat) : null,
            'revokedby' => $record->revokedby,
            'revocationreason' => $record->revocationreason,
            'verificationtoken' => $record->verificationtoken,
            'version' => $record->version,
            'payloadhash' => $record->payloadhash,
            'validationerrors' => $record->validationerrors,
            'errormessage' => $record->errormessage,
            'timecreated' => transform::datetime($record->timecreated),
            'timemodified' => transform::datetime($record->timemodified),
            'usermodified' => $record->usermodified,
        ];
    }

    /**
     * Prepares an audit log record for privacy export.
     *
     * @param \stdClass $record Audit log record.
     * @return \stdClass Data prepared for export.
     */
    protected static function prepare_log_for_export(\stdClass $record): \stdClass {
        return (object) [
            'credentialid' => $record->credentialid,
            'userid' => $record->userid,
            'courseid' => $record->courseid,
            'actorid' => $record->actorid,
            'action' => $record->action,
            'details' => $record->details,
            'timecreated' => transform::datetime($record->timecreated),
        ];
    }

    /**
     * Deletes audit logs related to credential records before the records are removed.
     *
     * @param array $records Credential records keyed by ID.
     * @param array $params Parameters for the optional user filter.
     * @param string|null $userwhere Optional SQL filter for user-related audit records.
     * @return void
     */
    protected static function delete_logs_for_records(array $records, array $params, ?string $userwhere = null): void {
        global $DB;

        $recordids = array_map(static fn($record) => (int) $record->id, $records);

        if (!empty($recordids)) {
            [$recordinsql, $recordparams] = $DB->get_in_or_equal($recordids, SQL_PARAMS_NAMED, 'credentialid');
            $DB->delete_records_select('local_edc_exporter_log', "credentialid {$recordinsql}", $recordparams);
        }

        if ($userwhere !== null) {
            $DB->delete_records_select('local_edc_exporter_log', $userwhere, $params);
            return;
        }

        if (array_key_exists('userid', $params) && array_key_exists('actorid', $params)) {
            $DB->delete_records_select(
                'local_edc_exporter_log',
                'courseid = :courseid AND (userid = :userid OR actorid = :actorid)',
                $params
            );
        }
    }

    /**
     * Deletes JSON files linked to a credential record.
     *
     * The plugin stores generated files under moodledata/local_edc_exporter/credentials.
     * This method only deletes files inside that safe directory.
     *
     * @param \stdClass $record Credential record.
     * @return void
     */
    protected static function delete_record_files(\stdClass $record): void {
        if (!empty($record->export_json_path)) {
            self::delete_safe_credential_file($record->export_json_path);
        }

        if (!empty($record->internal_json_path)) {
            self::delete_safe_credential_file($record->internal_json_path);
        }
    }

    /**
     * Deletes a credential file only if it is inside the expected plugin folder.
     *
     * This avoids deleting arbitrary server files if a path was corrupted.
     *
     * @param string $relativepath Relative file path stored in the database.
     * @return void
     */
    protected static function delete_safe_credential_file(string $relativepath): void {
        global $CFG;

        $relativepath = str_replace('\\', '/', $relativepath);
        $relativepath = ltrim($relativepath, '/');

        $basepath = $CFG->dataroot . '/local_edc_exporter/credentials';
        $filepath = $CFG->dataroot . '/' . $relativepath;

        $realbasepath = realpath($basepath);
        $realfilepath = realpath($filepath);

        if ($realbasepath === false || $realfilepath === false) {
            return;
        }

        $realbasepath = rtrim(str_replace('\\', '/', $realbasepath), '/') . '/';
        $realfilepath = str_replace('\\', '/', $realfilepath);

        if (strpos($realfilepath, $realbasepath) !== 0) {
            return;
        }

        if (is_file($realfilepath)) {
            unlink($realfilepath);
        }
    }
}
