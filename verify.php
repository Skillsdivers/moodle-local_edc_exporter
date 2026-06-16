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
 * Public credential verification page.
 *
 * This page intentionally does not require login. It only exposes the minimum
 * public data needed to verify the status of a credential by its random token.
 *
 * @package    local_edc_exporter
 * @copyright  2026 Skills Divers
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- Public credential verification page by token.
require(__DIR__ . '/../../config.php');

// This verification page is intentionally public.
// Anyone with a valid verification token can check the basic credential status.
$PAGE->set_context(context_system::instance());

use local_edc_exporter\local\audit\audit_logger;
use local_edc_exporter\local\ui\output_helper;

$token = required_param('token', PARAM_ALPHANUMEXT);

// Basic public rate limiting for invalid verification attempts.
// The counter is stored per remote IP and expires automatically after 5 minutes.
$ratelimitcache = cache::make('local_edc_exporter', 'verifyratelimit');
$ratelimitkey = sha1((string) getremoteaddr());
$failedattempts = (int) $ratelimitcache->get($ratelimitkey);

if ($failedattempts >= 10) {
    sleep(2);
}

$PAGE->set_url(output_helper::verify_url($token));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('verifytitle', 'local_edc_exporter'));
$PAGE->set_heading(get_string('verifytitle', 'local_edc_exporter'));
$PAGE->requires->css(output_helper::css_url());

echo $OUTPUT->header();
echo html_writer::start_div('edc-view');
echo html_writer::start_div('edc-page-header');
echo html_writer::start_div();
echo html_writer::div(get_string('verifytitle', 'local_edc_exporter'), 'edc-title');
echo html_writer::div(get_string('verifyintro', 'local_edc_exporter'), 'edc-subtitle');
echo html_writer::end_div();
echo html_writer::end_div();

$record = $DB->get_record('local_edc_exporter_cred', ['verificationtoken' => $token]);

if (!$record) {
    // Count only failed token lookups. Valid verification links are not penalised.
    $ratelimitcache->set($ratelimitkey, $failedattempts + 1);

    echo html_writer::start_div('edc-card edc-verify-card');
    echo html_writer::div(
        get_string('credentialnotfoundorinvalid', 'local_edc_exporter'),
        'edc-verify-status edc-verify-invalid'
    );
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

// A valid token resets the failed-attempt counter for this IP.
$ratelimitcache->delete($ratelimitkey);

$user = $DB->get_record(
    'user',
    ['id' => $record->userid],
    'id,firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename'
);
$course = $DB->get_record('course', ['id' => $record->courseid], 'id,fullname');

if (!$user || !$course) {
    echo html_writer::start_div('edc-card edc-verify-card');
    echo html_writer::div(
        get_string('credentialnotfoundorinvalid', 'local_edc_exporter'),
        'edc-verify-status edc-verify-invalid'
    );
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

// Register public verification. actorid is null because this page does not require login.
// The token itself is not stored in the audit log.
audit_logger::log(
    audit_logger::ACTION_VERIFIED,
    (int) $record->id,
    (int) $record->userid,
    (int) $record->courseid,
    null,
    [
        'result' => 'found',
        'status' => (string) $record->status,
    ]
);

$now = time();
$isrevoked = ($record->status === output_helper::STATUS_REVOKED);
$isexpired = (!$isrevoked && !empty($record->expiresat) && (int) $record->expiresat < $now);

if ($isrevoked) {
    $statuslabel = get_string('revokedcredential', 'local_edc_exporter');
    $statusclass = 'edc-verify-revoked';
} else if ($isexpired) {
    $statuslabel = get_string('expiredcredential', 'local_edc_exporter');
    $statusclass = 'edc-verify-expired';
} else {
    $statuslabel = get_string('validcredential', 'local_edc_exporter');
    $statusclass = 'edc-verify-valid';
}

$issuer = trim((string) get_config('local_edc_exporter', 'awarding_body_legal_name'));
if ($issuer === '') {
    $issuer = get_site()->fullname;
}

$coursecontext = context_course::instance((int) $course->id);
$issuedat = !empty($record->issuedat) ? (int) $record->issuedat : (int) $record->timemodified;

$metadataitems = [
    get_string('student', 'local_edc_exporter') => s(fullname($user)),
    get_string('course', 'moodle') => format_string($course->fullname, true, ['context' => $coursecontext]),
    get_string('issuername', 'local_edc_exporter') => s($issuer),
    get_string('issuedat', 'local_edc_exporter') => $issuedat > 0 ? userdate($issuedat) : '-',
    get_string('credentialstatus', 'local_edc_exporter') => s($statuslabel),
];

if (!empty($record->expiresat)) {
    $metadataitems[get_string('expiresat', 'local_edc_exporter')] = userdate((int) $record->expiresat);
}

if ($isrevoked && !empty($record->revokedat)) {
    $metadataitems[get_string('revokedat', 'local_edc_exporter')] = userdate((int) $record->revokedat);
}

echo html_writer::start_div('edc-card edc-verify-card');
echo html_writer::div($statuslabel, 'edc-verify-status ' . $statusclass);
echo html_writer::start_div('edc-meta');

foreach ($metadataitems as $title => $value) {
    echo html_writer::start_div('edc-meta-item');
    echo html_writer::div($title, 'edc-meta-title');
    echo html_writer::div($value, 'edc-meta-value');
    echo html_writer::end_div();
}

echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer();
