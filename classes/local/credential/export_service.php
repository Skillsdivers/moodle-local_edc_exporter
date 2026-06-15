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

namespace local_edc_exporter\local\credential;

use core\event\course_completed;
use local_edc_exporter\local\setup\configuration_status;
use local_edc_exporter\local\ui\output_helper;

/**
 * Application service for generating EDC credentials from course completions.
 *
 * Full flow:
 * - receives Moodle's course_completed event;
 * - creates or reuses the local_edcexport_cred record;
 * - collects user, course, grade, metadata, and administrative settings;
 * - builds export_json and internal_json through jsonld_builder;
 * - validates export_json before marking the credential as generated;
 * - stores JSON strings in the database and physical files in moodledata.
 *
 * This class does not render screens. Plugin web pages are responsible for
 * checking login, session, and permissions before calling this service.
 *
 * @package    local_edc_exporter
 * @copyright  2026 Skills Divers
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class export_service {
    /**
     * Generates or regenerates an EDC credential when Moodle reports a course completion.
     *
     * @param course_completed $event Course completion event.
     * @return void
     */
    public static function handle_course_completed(course_completed $event): void {
        global $DB, $CFG;

        // Loads Moodle grade functions because this service can run from a web page,
        // CLI, or an automatic event.
        require_once($CFG->dirroot . '/grade/querylib.php');
        require_once($CFG->libdir . '/gradelib.php');

        // ID of the learner who completed the course.
        $userid = (int) $event->relateduserid;
        // ID of the completed course.
        $courseid = (int) $event->courseid;
        // ID of the completion record stored by Moodle in course_completions.
        $completionid = (int) $event->objectid;
        configuration_status::require_course_ready($courseid);
        // Current timestamp used to create or update time fields.
        $now = time();

        // Checks that the completion really exists before generating anything.
        $completion = $DB->get_record('course_completions', ['id' => $completionid], '*', MUST_EXIST);

        // Finds an existing credential to avoid duplicates for the same learner and course.
        $record = $DB->get_record('local_edcexport_cred', ['userid' => $userid, 'courseid' => $courseid]);

        if ($record && $record->status === output_helper::STATUS_REVOKED) {
            throw new \moodle_exception('alreadyrevoked', 'local_edc_exporter');
        }

        if (!$record) {
            // First generation: creates the base record and leaves JSON empty until it is built.
            // $USER is reliable only when generation comes from a web session.
            // Automatic events or CLI runs may not have an active user.
            global $USER;
            $usermodified = !empty($USER->id) ? (int) $USER->id : null;

            $record = (object) [
                'userid' => $userid,
                'courseid' => $courseid,
                'completionid' => $completionid,
                'credentialid' => self::generate_uuid_v4(),
                'status' => 'pending',
                'internal_json' => null,
                'export_json' => null,
                'internal_json_path' => null,
                'export_json_path' => null,
                'payloadhash' => null,
                'issuedat' => null,
                'expiresat' => null,
                'revokedat' => null,
                'revokedby' => null,
                'revocationreason' => null,
                // Secure token for the public verify.php page.
                'verificationtoken' => self::generate_unique_verification_token(),
                // First credential version.
                'version' => 1,
                // User who triggers generation when a web session exists.
                'usermodified' => $usermodified,
                'validationerrors' => null,
                'errormessage' => null,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $record->id = $DB->insert_record('local_edcexport_cred', $record);
        } else {
            // If a credential already exists for this user and course, it will be regenerated.
            // Before creating the new version, previous JSON files for the same user and course
            // are deleted to avoid accumulating old certificates in moodledata.
            credential_file_manager::delete_existing_credential_files($record);

            // Reuse the same database row because the plugin has a unique user+course relation.
            // Only the credential content and files change.
            $record->completionid = $completionid;
            $record->status = output_helper::STATUS_PROCESSING;

            // Each regeneration increments the version without creating another row.
            $record->version = empty($record->version) ? 1 : ((int) $record->version + 1);

            // If an old credential had no token, create one now.
            if (empty($record->verificationtoken)) {
                $record->verificationtoken = self::generate_unique_verification_token();
            }

            // Records who launched regeneration when a web session exists.
            global $USER;
            $record->usermodified = !empty($USER->id) ? (int) $USER->id : null;

            // Clears previous data to avoid Moodle showing old paths if the new generation
            // fails during validation or file writing.
            $record->internal_json = null;
            $record->export_json = null;
            $record->internal_json_path = null;
            $record->export_json_path = null;
            $record->payloadhash = null;
            $record->validationerrors = null;
            $record->errormessage = null;
            $record->timemodified = $now;

            $DB->update_record('local_edcexport_cred', $record);
        }

        // Learner data displayed in the credential.
        $user = $DB->get_record('user', ['id' => $userid], 'id,username,firstname,lastname,email', MUST_EXIST);
        // Basic data for the course being certified.
        $course = $DB->get_record('course', ['id' => $courseid], 'id,shortname,fullname,summary', MUST_EXIST);
        // Learner final grade in this course, if Moodle has it available.
        $gradeinfo = self::get_grade_info($userid, $courseid);
        // Course custom fields such as hours, learning outcomes, or modality.
        $metadata = course_metadata::get_course_custom_fields($courseid);
        // Global plugin settings such as legal name and issuer country.
        $settings = self::get_plugin_settings();

        // Stores the completion date that will be included in the credential.
        // If Moodle provides no date, the current timestamp is used to avoid an empty field.
        $record->completiontime = (int) ($completion->timecompleted ?: $now);

        // Builds both documents as PHP arrays before converting them to JSON text.
        $payloads = jsonld_builder::build($user, $course, $record, $gradeinfo, $metadata, $settings);

        // Converts arrays to JSON. The options keep the text readable and indented.
        $exportjson = json_encode(
            $payloads['export_json'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );
        $internaljson = json_encode(
            $payloads['internal_json'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );

        // Validates only the official export JSON. Internal JSON contains extra Moodle data.
        $errors = schema_validator::validate($payloads['export_json']);

        // Prepares the data that will be stored in the database.
        $record->internal_json = $internaljson;
        $record->export_json = $exportjson;
        // Official JSON fingerprint: helps detect whether the content changed.
        $record->payloadhash = hash('sha256', $exportjson);
        // Complete errors in JSON format, or null when there are no errors.
        $record->validationerrors = empty($errors)
            ? null
            : json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        // Short error message to display in the interface.
        $record->errormessage = empty($errors) ? null : implode(' | ', $errors);
        // Uses the same timestamp for the whole operation to avoid internal differences.
        $record->timemodified = $now;

        if (!empty($errors)) {
            // If important data is missing, mark as error and do not write downloadable files.
            $record->status = output_helper::STATUS_ERROR;
            $DB->update_record('local_edcexport_cred', $record);
            debugging(
                '[local_edc_exporter] Validation error for user ' . $userid . ', course ' . $courseid . ': ' .
                    $record->errormessage,
                DEBUG_DEVELOPER
            );
            return;
        }

        // Relative paths stored in the database, so they do not depend on the real moodledata path.
        $exportrelativepath = credential_file_manager::build_relative_path($courseid, $userid, $record->credentialid, 'export');
        $internalrelativepath = credential_file_manager::build_relative_path($courseid, $userid, $record->credentialid, 'internal');
        $compatrelativepath = credential_file_manager::build_relative_path(
            $courseid,
            $userid,
            $record->credentialid,
            'compat_export'
        );

        // Full server paths where files are written.
        $exportfullpath = credential_file_manager::build_full_path($exportrelativepath);
        $internalfullpath = credential_file_manager::build_full_path($internalrelativepath);
        $compatfullpath = credential_file_manager::build_full_path($compatrelativepath);

        // Creates the required folders before storing files.
        credential_file_manager::ensure_directory(dirname($exportfullpath));
        credential_file_manager::ensure_directory(dirname($internalfullpath));
        credential_file_manager::ensure_directory(dirname($compatfullpath));

        if (file_put_contents($exportfullpath, $exportjson) === false) {
            throw new \moodle_exception('filenotwritable', 'local_edc_exporter', '', $exportrelativepath);
        }

        if (file_put_contents($internalfullpath, $internaljson) === false) {
            throw new \moodle_exception('filenotwritable', 'local_edc_exporter', '', $internalrelativepath);
        }

        if (file_put_contents($compatfullpath, $exportjson) === false) {
            throw new \moodle_exception('filenotwritable', 'local_edc_exporter', '', $compatrelativepath);
        }

        // Stores relative paths and marks the credential as ready.
        $record->export_json_path = $exportrelativepath;
        $record->internal_json_path = $internalrelativepath;
        $record->status = output_helper::STATUS_GENERATED;

        // The issue date is updated only when generation completes successfully.
        // Uses $now so issuedat and timemodified remain consistent in the same operation.
        $record->issuedat = $now;
        $record->timemodified = $now;

        // Records the modifying user only when a web session is available.
        global $USER;
        $record->usermodified = !empty($USER->id) ? (int) $USER->id : null;

        // From this point, status=generated plus export_json_path identifies a credential
        // that is ready for viewing and download.
        $DB->update_record('local_edcexport_cred', $record);
    }

    /**
     * Gets the course final grade as a simple array when it exists.
     *
     * When Moodle returns no grade, the builder omits the grade section.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @return array Course grade data.
     */
    private static function get_grade_info(int $userid, int $courseid): array {
        $grade = grade_get_course_grade($userid, $courseid);
        if ($grade && isset($grade->grade) && $grade->grade !== null) {
            return [
                'grade' => (float) $grade->grade,
                'min' => isset($grade->grademin) ? (float) $grade->grademin : null,
                'max' => isset($grade->grademax) ? (float) $grade->grademax : null,
                'str' => $grade->str_grade ?? null,
            ];
        }

        return [];
    }

    /**
     * Gets plugin settings that describe the issuer/awarding body.
     *
     * These values describe the issuing organisation and control whether local URLs are allowed.
     *
     * @return array Normalised plugin settings.
     */
    private static function get_plugin_settings(): array {
        global $CFG;

        $homepage = trim((string) get_config('local_edc_exporter', 'awarding_body_homepage'));
        if ($homepage === '') {
            $homepage = rtrim($CFG->wwwroot, '/');
        }

        $legalname = trim((string) get_config('local_edc_exporter', 'awarding_body_legal_name'));

        return [
            'awarding_body_legal_name' => $legalname ?: get_site()->fullname,
            'awarding_body_country' => trim((string) get_config('local_edc_exporter', 'awarding_body_country')),
            'awarding_body_country_label' => trim((string) get_config('local_edc_exporter', 'awarding_body_country_label')),
            'awarding_body_homepage' => $homepage,
            'awarding_body_identifier' => trim((string) get_config('local_edc_exporter', 'awarding_body_identifier')),
            'include_urls_when_localhost' => (int) get_config('local_edc_exporter', 'include_urls_when_localhost'),
        'display_mode' => trim((string) get_config('local_edc_exporter', 'display_mode')),
        'display_custom_html' => (string) get_config('local_edc_exporter', 'display_custom_html'),

        // Visual template builder settings.
        // These values are passed to cover_image_builder so the generated image
        // reflects the administrator's selected design.
        'display_template_background' => trim((string) get_config('local_edc_exporter', 'display_template_background')),

        // Custom background colour used only when the selected background is "customcolor".
        'display_template_background_customcolor' =>
            trim((string) get_config('local_edc_exporter', 'display_template_background_customcolor')),
        ];
    }

    /**
     * Generates a UUID v4 for credentialid.
     *
     * Version and variant bits are set after random_bytes, following the standard
     * UUID format without external dependencies.
     *
     * @return string UUID v4.
     */
    private static function generate_uuid_v4(): string {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Generates a unique token to publicly verify a credential.
     *
     * Moodle internal ID is not used to avoid exposing predictable information
     * on the public verify.php page.
     *
     * @return string Secure 64-character hexadecimal token.
     */
    private static function generate_unique_verification_token(): string {
        global $DB;

        do {
            $token = bin2hex(random_bytes(32));
        } while ($DB->record_exists('local_edcexport_cred', ['verificationtoken' => $token]));

        return $token;
    }
}
