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
 * Displays the visual summary of a generated EDC credential.
 *
 * @package    local_edc_exporter
 * @copyright  2026 Skills Divers
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_edc_exporter\local\audit\audit_logger;
use local_edc_exporter\local\setup\configuration_status;
use local_edc_exporter\local\ui\output_helper;

$id = required_param('id', PARAM_INT);

$record = $DB->get_record('local_edcexport_cred', ['id' => $id], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $record->courseid], '*', MUST_EXIST);

require_login($course);

$user = $DB->get_record('user', ['id' => $record->userid], '*', MUST_EXIST);

$context = context_course::instance($course->id);
require_capability('local/edc_exporter:view', $context);

$canviewinternal = has_capability('local/edc_exporter:downloadinternal', $context);
$fieldreport = [];
$coursestatus = configuration_status::course_status((int) $course->id);

if ($canviewinternal && !empty($record->internal_json)) {
    $internaldata = json_decode($record->internal_json, true);

    if (is_array($internaldata) && !empty($internaldata['field_mapping_report'])) {
        $fieldreport = $internaldata['field_mapping_report'];
    }
}

if ($record->status !== output_helper::STATUS_GENERATED && $record->status !== output_helper::STATUS_REVOKED) {
    throw new moodle_exception('credentialnotgenerated', 'local_edc_exporter');
}

$PAGE->set_url(output_helper::view_url($id));
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string('microcredential', 'local_edc_exporter'));
$PAGE->set_heading($course->fullname);
$PAGE->requires->css(output_helper::css_url());

// Register that the credential page was viewed.
// This stores only ids and action type, not credential JSON or personal data.
audit_logger::log(
    audit_logger::ACTION_VIEWED,
    (int) $record->id,
    (int) $record->userid,
    (int) $record->courseid,
    (int) $USER->id
);

echo $OUTPUT->header();

echo html_writer::start_div('edc-view');
echo html_writer::start_div('edc-page-header');
echo html_writer::start_div();
echo html_writer::div(get_string('microcredential', 'local_edc_exporter'), 'edc-title');
echo html_writer::div(format_string($course->fullname, true, ['context' => $context]), 'edc-subtitle');
echo html_writer::end_div();
echo html_writer::start_div('edc-header-actions');
if ($record->status === output_helper::STATUS_GENERATED) {
    echo html_writer::link(
        output_helper::download_url((int) $record->id, 'export'),
        get_string('downloadedc', 'local_edc_exporter'),
        ['class' => 'edc-btn edc-btn-primary']
    );
}
if (has_capability('local/edc_exporter:revoke', $context) && $record->status === output_helper::STATUS_GENERATED) {
    echo html_writer::link(
        output_helper::revoke_url((int) $record->id),
        get_string('revokecredential', 'local_edc_exporter'),
        ['class' => 'edc-btn edc-btn-light']
    );
}
echo html_writer::link(
    output_helper::list_url((int) $record->courseid),
    get_string('backtolist', 'local_edc_exporter'),
    ['class' => 'edc-btn edc-btn-secondary']
);
echo html_writer::end_div();
echo html_writer::end_div();

if ($canviewinternal && !$coursestatus['ready']) {
    echo $OUTPUT->notification(get_string('legacycredentialwarning', 'local_edc_exporter'), 'warning');
}

if ($record->status === output_helper::STATUS_REVOKED) {
    echo $OUTPUT->notification(get_string('revokedcredentialnotice', 'local_edc_exporter'), 'error');
}

echo html_writer::start_div('edc-card');

$issuerlogo = output_helper::issuer_logo_url();

if ($issuerlogo !== null) {
    echo html_writer::div(
        html_writer::empty_tag('img', [
            'src' => $issuerlogo->out(false),
            'alt' => get_string('issuerlogoalt', 'local_edc_exporter'),
            'class' => 'edc-issuer-logo',
        ]),
        'edc-issuer-logo-wrap'
    );
}

echo html_writer::div(get_string('issuername', 'local_edc_exporter'), 'edc-kicker');
echo html_writer::tag('h1', get_string('microcredential', 'local_edc_exporter'), ['class' => 'edc-view-title']);

echo html_writer::div(get_string('awardedto', 'local_edc_exporter'), 'edc-label');
echo html_writer::div(s(fullname($user)), 'edc-name');

echo html_writer::div(get_string('completed', 'local_edc_exporter'), 'edc-label');
echo html_writer::div(format_string($course->fullname, true, ['context' => $context]), 'edc-course');

echo html_writer::start_div('edc-meta');

$metadataitems = [
    get_string('status', 'local_edc_exporter') => s($record->status),
    get_string('issued', 'local_edc_exporter') => userdate(!empty($record->issuedat) ? $record->issuedat : $record->timemodified),
    get_string('courseid', 'local_edc_exporter') => (int) $record->courseid,
    get_string('credentialid', 'local_edc_exporter') => s($record->credentialid),
];

if ($record->status === output_helper::STATUS_REVOKED) {
    $revokedby = '-';
    if (!empty($record->revokedby)) {
        $revoker = $DB->get_record('user', ['id' => $record->revokedby], 'id,firstname,lastname,email');
        if ($revoker) {
            $revokedby = s(fullname($revoker));
        }
    }

    $metadataitems[get_string('revokedat', 'local_edc_exporter')] = !empty($record->revokedat)
        ? userdate($record->revokedat)
        : '-';
    $metadataitems[get_string('revokedby', 'local_edc_exporter')] = $revokedby;
    $metadataitems[get_string('revokedreason', 'local_edc_exporter')] = s((string) $record->revocationreason);
}

if (!empty($record->verificationtoken)) {
    $verifyurl = output_helper::verify_url((string) $record->verificationtoken);
    $metadataitems[get_string('verificationurl', 'local_edc_exporter')] = html_writer::link(
        $verifyurl,
        s($verifyurl->out(false)),
        ['target' => '_blank', 'rel' => 'noopener noreferrer']
    );
}

foreach ($metadataitems as $title => $value) {
    echo html_writer::start_div('edc-meta-item');
    echo html_writer::div($title, 'edc-meta-title');
    echo html_writer::div($value, 'edc-meta-value');
    echo html_writer::end_div();
}

echo html_writer::end_div();

if (!empty($fieldreport)) {
    echo html_writer::start_tag('details', ['class' => 'edc-field-report edc-details']);
    echo html_writer::tag('summary', get_string('fieldmappingreport', 'local_edc_exporter'));
    echo html_writer::tag('h2', get_string('fieldmappingreport', 'local_edc_exporter'));
    echo html_writer::tag('p', get_string('fieldmappingreport_desc', 'local_edc_exporter'));

    echo html_writer::start_tag('table', ['class' => 'generaltable']);
    echo html_writer::start_tag('thead');

    $headerrow = html_writer::tag('th', get_string('field', 'local_edc_exporter'), ['scope' => 'col'])
        . html_writer::tag('th', get_string('source', 'local_edc_exporter'), ['scope' => 'col'])
        . html_writer::tag('th', get_string('required', 'local_edc_exporter'), ['scope' => 'col'])
        . html_writer::tag('th', get_string('status', 'local_edc_exporter'), ['scope' => 'col'])
        . html_writer::tag('th', get_string('valuepreview', 'local_edc_exporter'), ['scope' => 'col']);

    echo html_writer::tag('tr', $headerrow);
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');

    foreach ($fieldreport as $field) {
        $rowclass = (!empty($field['required']) && empty($field['found'])) ? 'edc-missing-required' : '';

        $requiredlabel = !empty($field['required'])
            ? get_string('yes', 'local_edc_exporter')
            : get_string('no', 'local_edc_exporter');

        if (!empty($field['found'])) {
            $statuslabel = get_string('found', 'local_edc_exporter');
        } else if (!empty($field['required'])) {
            $statuslabel = get_string('missingrequired', 'local_edc_exporter');
        } else {
            $statuslabel = get_string('missingoptional', 'local_edc_exporter');
        }

        $preview = '';
        if (!empty($field['value_preview'])) {
            $shortpreview = nl2br(s($field['value_preview']));

            if (
                !empty($field['full_value'])
                && !empty($field['value_preview'])
                && $field['full_value'] !== $field['value_preview']
            ) {
                $summary = html_writer::span($shortpreview, 'edc-preview-short')
                    . html_writer::span(get_string('showmore', 'local_edc_exporter'), 'edc-preview-toggle');

                $preview = html_writer::tag(
                    'details',
                    html_writer::tag('summary', $summary)
                        . html_writer::div(nl2br(s($field['full_value'])), 'edc-preview-full'),
                    ['class' => 'edc-preview-details']
                );
            } else {
                $preview = html_writer::div($shortpreview, 'edc-preview-short');
            }
        }

        $row = html_writer::tag('td', s($field['label']))
            . html_writer::tag('td', s($field['source']))
            . html_writer::tag('td', $requiredlabel)
            . html_writer::tag('td', $statuslabel)
            . html_writer::tag('td', $preview);

        echo html_writer::tag('tr', $row, ['class' => $rowclass]);
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_tag('details');
}

$actions = html_writer::link(
    new moodle_url('/course/view.php', ['id' => $record->courseid]),
    get_string('backtocourse', 'local_edc_exporter'),
    ['class' => 'edc-download']
);

echo html_writer::div(
    html_writer::div($actions, 'edc-view-action-links'),
    'edc-view-actions'
);

echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer();
