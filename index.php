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
 * Displays generated EDC credentials for a course.
 *
 * @package    local_edc_exporter
 * @copyright  2026 Skills Divers
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_edc_exporter\local\setup\configuration_status;
use local_edc_exporter\local\ui\output_helper;

$courseid = required_param('courseid', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 25;

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);

$context = context_course::instance($courseid);
require_capability('local/edc_exporter:view', $context);

$PAGE->set_url(output_helper::list_url($courseid));
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string('pluginname', 'local_edc_exporter'));
$PAGE->set_heading($course->fullname);
$PAGE->requires->css(output_helper::css_url());

echo $OUTPUT->header();

$coursestatus = configuration_status::course_status($courseid);

$totalrecords = $DB->count_records('local_edcexport_cred', ['courseid' => $courseid]);
$generatedcount = $DB->count_records('local_edcexport_cred', [
    'courseid' => $courseid,
    'status' => output_helper::STATUS_GENERATED,
]);
$errorcount = $DB->count_records('local_edcexport_cred', [
    'courseid' => $courseid,
    'status' => output_helper::STATUS_ERROR,
]);

$sql = "SELECT c.*, u.firstname, u.lastname, u.email
          FROM {local_edcexport_cred} c
          JOIN {user} u ON u.id = c.userid
         WHERE c.courseid = :courseid
      ORDER BY c.timecreated DESC";

$records = $DB->get_records_sql(
    $sql,
    ['courseid' => $courseid],
    $page * $perpage,
    $perpage
);

$completedusers = $DB->get_records_sql(
    "SELECT cc.userid
       FROM {course_completions} cc
      WHERE cc.course = :courseid
        AND cc.timecompleted IS NOT NULL",
    ['courseid' => $courseid]
);

$pendingcount = 0;
foreach ($completedusers as $completeduser) {
    $existing = $DB->get_record('local_edcexport_cred', [
        'userid' => $completeduser->userid,
        'courseid' => $courseid,
    ]);

    if ($existing && $existing->status === output_helper::STATUS_REVOKED) {
        continue;
    }

    if (!$existing || $existing->status !== output_helper::STATUS_GENERATED || empty($existing->export_json_path)) {
        $pendingcount++;
    }
}

echo html_writer::start_div('edc-wrapper');
echo html_writer::start_div('edc-page-header');
echo html_writer::start_div();

echo html_writer::div(get_string('pluginname', 'local_edc_exporter'), 'edc-title');

$subtitle = get_string('generatedcredentialsforcourse', 'local_edc_exporter') . ' ' .
    html_writer::tag('strong', format_string($course->fullname, true, ['context' => $context]));
echo html_writer::div($subtitle, 'edc-subtitle');

echo html_writer::end_div();
echo html_writer::start_div('edc-header-actions');

if (has_capability('local/edc_exporter:generatepending', $context) || is_siteadmin()) {
    echo html_writer::link(
        output_helper::setup_url($courseid),
        get_string('setupassistant', 'local_edc_exporter'),
        ['class' => 'edc-btn edc-btn-primary']
    );
}

if (is_siteadmin()) {
    echo html_writer::link(
        new moodle_url('/admin/settings.php', ['section' => 'local_edc_exporter']),
        get_string('pluginsettings', 'local_edc_exporter'),
        ['class' => 'edc-btn edc-btn-secondary']
    );
}

echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('edc-dashboard');
echo html_writer::div(
    html_writer::span((string) $generatedcount, 'edc-stat-value') .
    html_writer::span(get_string('stat_generated', 'local_edc_exporter'), 'edc-stat-label'),
    'edc-stat'
);
echo html_writer::div(
    html_writer::span((string) $pendingcount, 'edc-stat-value') .
    html_writer::span(get_string('stat_pending', 'local_edc_exporter'), 'edc-stat-label'),
    'edc-stat'
);
echo html_writer::div(
    html_writer::span((string) $errorcount, 'edc-stat-value') .
    html_writer::span(get_string('stat_errors', 'local_edc_exporter'), 'edc-stat-label'),
    'edc-stat'
);
echo html_writer::end_div();

$cangeneratepending = has_capability('local/edc_exporter:generatepending', $context);
$caneditcourse = has_capability('moodle/course:update', $context);

if ($pendingcount > 0 && !$coursestatus['ready']) {
    $blockingitems = configuration_status::blocking_items($coursestatus);
    $actions = [];

    if ($cangeneratepending || is_siteadmin()) {
        $actions[] = output_helper::action_link(
            output_helper::setup_url($courseid),
            get_string('viewsetupsteps', 'local_edc_exporter'),
            'edc-btn-primary'
        );
    }

    if (is_siteadmin()) {
        $actions[] = output_helper::action_link(
            new moodle_url('/local/edc_exporter/create_fields.php', ['sesskey' => sesskey()]),
            get_string('createrecommendedfields', 'local_edc_exporter'),
            'edc-btn-secondary'
        );
        $actions[] = output_helper::action_link(
            new moodle_url('/admin/settings.php', ['section' => 'local_edc_exporter']),
            get_string('pluginsettings', 'local_edc_exporter'),
            'edc-btn-light'
        );
    }

    if ($caneditcourse) {
        $actions[] = output_helper::action_link(
            new moodle_url('/course/edit.php', ['id' => $courseid]),
            get_string('editcourse', 'local_edc_exporter'),
            'edc-btn-light'
        );
    }

    $missinglist = '';
    if (!empty($blockingitems)) {
        $itemshtml = [];
        foreach ($blockingitems as $item) {
            $itemshtml[] = html_writer::tag(
                'li',
                html_writer::tag('strong', s($item['title'])) . ': ' . s($item['message'])
            );
        }
        $missinglist = html_writer::tag('ul', implode('', $itemshtml), ['class' => 'edc-blocked-list']);
    }

    echo html_writer::start_div('edc-generation-blocked');
    echo html_writer::div(get_string('generationblockedtitle', 'local_edc_exporter'), 'edc-generation-blocked-title');
    echo html_writer::div(
        get_string('generationblockedpending', 'local_edc_exporter', (int) $pendingcount),
        'edc-generation-blocked-message'
    );
    echo $missinglist;

    if (!empty($actions)) {
        echo html_writer::div(implode(' ', array_unique($actions)), 'edc-generation-blocked-actions');
    }

    echo html_writer::end_div();
}

if (has_capability('local/edc_exporter:generatepending', $context) && $pendingcount > 0 && $coursestatus['ready']) {
    echo html_writer::div(
        output_helper::action_link(
            output_helper::generate_pending_url($courseid),
            get_string('generatependingwithcount', 'local_edc_exporter') . ' (' . (int) $pendingcount . ')',
            'edc-btn-primary',
            ['onclick' => 'return confirm(' . json_encode(get_string('confirmgeneratepending', 'local_edc_exporter')) . ')']
        ),
        'edc-toolbar'
    );
}

if (empty($records)) {
    if (empty($completedusers)) {
        echo $OUTPUT->notification(get_string('nocompletedusers', 'local_edc_exporter'), 'info');
    } else if ($pendingcount > 0 && !$coursestatus['ready']) {
        echo $OUTPUT->notification(get_string('nocredentialsblocked', 'local_edc_exporter'), 'warning');
    } else {
        echo $OUTPUT->notification(get_string('nocredentials', 'local_edc_exporter'), 'info');
    }
} else {
    echo html_writer::start_tag('table', ['class' => 'edc-table']);
    echo html_writer::start_tag('thead');

    $headerrow = html_writer::tag('th', get_string('student', 'local_edc_exporter'), ['scope' => 'col'])
        . html_writer::tag('th', get_string('email', 'local_edc_exporter'), ['scope' => 'col'])
        . html_writer::tag('th', get_string('status', 'local_edc_exporter'), ['scope' => 'col'])
        . html_writer::tag('th', get_string('timecreated', 'local_edc_exporter'), ['scope' => 'col'])
        . html_writer::tag('th', get_string('actions', 'local_edc_exporter'), ['scope' => 'col']);

    echo html_writer::tag('tr', $headerrow);
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');

    foreach ($records as $record) {
        $actions = [];

        if ($record->status === output_helper::STATUS_GENERATED || $record->status === output_helper::STATUS_REVOKED) {
            $actions[] = output_helper::action_link(
                output_helper::view_url((int) $record->id),
                get_string('viewcredential', 'local_edc_exporter'),
                'edc-btn-secondary'
            );
        }

        if (
            has_capability('local/edc_exporter:download', $context)
            && $record->status === output_helper::STATUS_GENERATED
        ) {
            $actions[] = output_helper::action_link(
                output_helper::download_url((int) $record->id, 'export'),
                get_string('exportjson', 'local_edc_exporter'),
                'edc-btn-primary',
                ['title' => get_string('exportjson_desc', 'local_edc_exporter')]
            );
        }

        if (
            has_capability('local/edc_exporter:revoke', $context)
            && $record->status === output_helper::STATUS_GENERATED
        ) {
            $actions[] = output_helper::action_link(
                output_helper::revoke_url((int) $record->id),
                get_string('revokecredential', 'local_edc_exporter'),
                'edc-btn-light',
                ['title' => get_string('revokecredential', 'local_edc_exporter')]
            );
        }

        if (has_capability('local/edc_exporter:downloadinternal', $context)) {
            // Internal_json includes source_data and Moodle tracking, so it has a separate capability.
            $actions[] = output_helper::action_link(
                output_helper::download_url((int) $record->id, 'internal'),
                get_string('internaljson', 'local_edc_exporter'),
                'edc-btn-light',
                ['title' => get_string('internaljson_desc', 'local_edc_exporter')]
            );
        }

        if (has_capability('local/edc_exporter:regenerate', $context) && $record->status === output_helper::STATUS_ERROR) {
            $actions[] = output_helper::action_link(
                output_helper::regenerate_url((int) $record->id),
                get_string('fixerror', 'local_edc_exporter'),
                'edc-btn-light',
                [
                    'title' => get_string('regenerate_desc', 'local_edc_exporter'),
                    'onclick' => 'return confirm(' . json_encode(get_string('confirmregenerate', 'local_edc_exporter')) . ')',
                ]
            );
        }

        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', s(fullname($record)));
        echo html_writer::tag('td', s($record->email));
        echo html_writer::tag(
            'td',
            html_writer::span(s($record->status), output_helper::status_class((string) $record->status))
        );
        echo html_writer::tag('td', userdate($record->timecreated));
        echo html_writer::tag(
            'td',
            html_writer::div(implode(' ', $actions), 'edc-action-group'),
            ['class' => 'edc-actions']
        );
        echo html_writer::end_tag('tr');

        if (
            has_capability('local/edc_exporter:regenerate', $context)
            && $record->status === output_helper::STATUS_ERROR
            && !empty($record->errormessage)
        ) {
            $errordetail = html_writer::div(
                html_writer::tag('strong', get_string('error', 'moodle') . ':') . ' ' . s($record->errormessage),
                'edc-error-box'
            );

            echo html_writer::tag(
                'tr',
                html_writer::tag('td', $errordetail, ['colspan' => 5, 'class' => 'edc-error-detail'])
            );
        }
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');

    echo $OUTPUT->paging_bar(
        $totalrecords,
        $page,
        $perpage,
        new moodle_url('/local/edc_exporter/index.php', [
            'courseid' => $courseid,
        ])
    );
}

echo html_writer::end_div();

echo $OUTPUT->footer();
