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
 * Revokes an issued EDC credential.
 *
 * @package    local_edc_exporter
 * @copyright  2026 Skills Divers
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_edc_exporter\form\revoke_form;
use local_edc_exporter\local\audit\audit_logger;
use local_edc_exporter\local\ui\output_helper;

$id = required_param('id', PARAM_INT);

$record = $DB->get_record('local_edc_exporter_cred', ['id' => $id], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $record->courseid], '*', MUST_EXIST);

require_login($course);

$context = context_course::instance($course->id);
require_capability('local/edc_exporter:revoke', $context);

$listurl = output_helper::list_url((int) $record->courseid);
$url = output_helper::revoke_url($id);

if ($record->status === output_helper::STATUS_REVOKED) {
    redirect(
        $listurl,
        get_string('alreadyrevoked', 'local_edc_exporter'),
        null,
        \core\output\notification::NOTIFY_WARNING
    );
}

// Moodle requires the page context and course to be configured before
// creating forms or generating any output. Otherwise the theme may be
// initialised too early and later calls to set_course() will fail.
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string('revokecredential', 'local_edc_exporter'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->css(output_helper::css_url());

// The form is created only after the page has been fully configured.
$mform = new revoke_form($url, ['id' => $id]);
$mform->set_data(['id' => $id]);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/edc_exporter/index.php', ['courseid' => $course->id]));
}

if ($data = $mform->get_data()) {
    // Validate the session key only when processing submitted revocation data.
    require_sesskey();

    // Store a plain-text reason to avoid saving unsafe HTML.
    $reason = trim((string) $data->revocationreason);
    if ($reason === '') {
        redirect(
            $url,
            get_string('cannotrevoke', 'local_edc_exporter'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    $now = time();

    // Mark the credential as revoked and keep a minimal traceability record.
    $record->status = output_helper::STATUS_REVOKED;
    $record->revokedat = $now;
    $record->revokedby = (int) $USER->id;
    $record->revocationreason = $reason;
    $record->usermodified = (int) $USER->id;
    $record->timemodified = $now;

    $DB->update_record('local_edc_exporter_cred', $record);

    // Register the revocation action without storing unnecessary personal data.
    audit_logger::log(
        audit_logger::ACTION_REVOKED,
        (int) $record->id,
        (int) $record->userid,
        (int) $record->courseid,
        (int) $USER->id,
        [
            'hasreason' => $reason !== '',
        ]
    );

    redirect(
        $listurl,
        get_string('credentialrevoked', 'local_edc_exporter'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo html_writer::start_div('edc-wrapper');
echo html_writer::tag('h2', get_string('revokecredential', 'local_edc_exporter'));
echo $OUTPUT->notification(get_string('confirmrevoke', 'local_edc_exporter'), 'warning');
$mform->display();
echo html_writer::end_div();
echo $OUTPUT->footer();
