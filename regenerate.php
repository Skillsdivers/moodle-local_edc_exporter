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
 * Regenerates an existing EDC credential.
 *
 * @package    local_edc_exporter
 * @copyright  2026 Skills Divers
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_edc_exporter\local\credential\export_service;
use local_edc_exporter\local\ui\output_helper;

$id = required_param('id', PARAM_INT);

$record = $DB->get_record('local_edc_exporter_cred', ['id' => $id], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $record->courseid], '*', MUST_EXIST);

require_login($course);
require_sesskey();

$context = context_course::instance($course->id);
require_capability('local/edc_exporter:regenerate', $context);

$completion = $DB->get_record('course_completions', ['id' => $record->completionid], '*', MUST_EXIST);

try {
    $event = \core\event\course_completed::create_from_completion($completion);
    export_service::handle_course_completed($event);

    redirect(
        output_helper::list_url((int) $record->courseid),
        get_string('credentialregenerated', 'local_edc_exporter'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
} catch (\Throwable $e) {
    debugging('[local_edc_exporter] Credential regeneration failed: ' . $e->getMessage(), DEBUG_DEVELOPER);

    $message = ($e instanceof \moodle_exception)
        ? $e->getMessage()
        : get_string('credentialregenerationfailed', 'local_edc_exporter');

    redirect(
        output_helper::list_url((int) $record->courseid),
        $message,
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}
