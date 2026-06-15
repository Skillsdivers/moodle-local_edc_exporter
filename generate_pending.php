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
 * Generates pending EDC credentials for completed users in a course.
 *
 * @package    local_edc_exporter
 * @copyright  2026 Skills Divers
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_edc_exporter\local\credential\export_service;
use local_edc_exporter\local\setup\configuration_status;
use local_edc_exporter\local\ui\output_helper;

// Course ID where completed learners without a useful credential will be found.
$courseid = required_param('courseid', PARAM_INT);

// Moodle course where the bulk action runs. It is loaded before require_login()
// so access is validated against the real course context.
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

require_login($course);
// Prevents bulk execution through CSRF from external links.
require_sesskey();

// Course context used for capability checks.
$context = context_course::instance($course->id);
// Separate capability from regenerate to control who can process pending credentials.
require_capability('local/edc_exporter:generatepending', $context);

// Existing completions in this course. Each completed record can generate a credential.
$completions = $DB->get_records('course_completions', ['course' => $courseid]);

// Number of credentials generated in this run.
$generated = 0;
// Number of completions skipped because they did not apply or already had a credential.
$skipped = 0;
// Number of errors caught during generation.
$errors = 0;

// Basic preflight validation for required fields.
// Prevents credential generation when required custom fields are missing.
try {
    configuration_status::require_course_ready($courseid);
} catch (\moodle_exception $e) {
    redirect(
        output_helper::list_url($courseid),
        $e->getMessage(),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}
foreach ($completions as $completion) {
    if (empty($completion->timecompleted)) {
        // Credentials are generated only for completed course completions.
        $skipped++;
        continue;
    }

    // Existing credential record for this learner and course, if present.
    $existing = $DB->get_record('local_edcexport_cred', [
        'userid' => $completion->userid,
        'courseid' => $courseid,
    ]);

    if ($existing && $existing->status === output_helper::STATUS_GENERATED && !empty($existing->export_json_path)) {
        // Credential is already ready, so it is not regenerated without an explicit request.
        $skipped++;
        continue;
    }

    if ($existing && $existing->status === output_helper::STATUS_REVOKED) {
        $skipped++;
        continue;
    }

    try {
        // Creates the same event type Moodle emits automatically.
        $event = \core\event\course_completed::create_from_completion($completion);
        // Reuses the main service to avoid duplicating generation logic.
        export_service::handle_course_completed($event);
        $generated++;
    } catch (\Throwable $e) {
        $errors++;

        $message = $e->getMessage();
        if (!is_string($message) || $message === '') {
            $message = get_class($e);
        }

        debugging('[local_edc_exporter] Pending credential generation failed: ' . $message, DEBUG_DEVELOPER);

        // If the service left a credential in processing before failing,
        // mark it as error so it is not visually stuck.
        $failedrecord = $DB->get_record('local_edcexport_cred', [
            'userid' => $completion->userid,
            'courseid' => $courseid,
        ]);

        if ($failedrecord && $failedrecord->status === output_helper::STATUS_PROCESSING) {
            $failedrecord->status = output_helper::STATUS_ERROR;
            $failedrecord->errormessage = $message;
            $failedrecord->timemodified = time();
            $DB->update_record('local_edcexport_cred', $failedrecord);
        }
    }
}

// Final message shown after returning to the credential list.
$message = get_string('generatependingresult', 'local_edc_exporter', (object) [
    'generated' => $generated,
    'skipped' => $skipped,
    'errors' => $errors,
]);

redirect(
    output_helper::list_url($courseid),
    $message,
    null,
    $errors > 0 ? \core\output\notification::NOTIFY_WARNING : \core\output\notification::NOTIFY_SUCCESS
);
