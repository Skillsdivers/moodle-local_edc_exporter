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
 * Quick help page for the EDC exporter plugin.
 *
 * @package    local_edc_exporter
 * @copyright  2026 Skills Divers
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_edc_exporter\local\ui\output_helper;

$courseid = optional_param('courseid', 0, PARAM_INT);
$course = null;

if ($courseid > 0) {
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    require_login($course);
    $context = context_course::instance($courseid);
    require_capability('local/edc_exporter:view', $context);
    $PAGE->set_course($course);
} else {
    require_login();
    $context = context_system::instance();
    require_capability('moodle/site:config', $context);
}

$PAGE->set_url(output_helper::help_url($courseid > 0 ? $courseid : null));
$PAGE->set_context($context);
$PAGE->set_title(get_string('quickhelp', 'local_edc_exporter'));
$PAGE->set_heading($course ? format_string($course->fullname) : get_string('pluginname', 'local_edc_exporter'));
$PAGE->requires->css(output_helper::css_url());

echo $OUTPUT->header();
echo html_writer::start_div('edc-wrapper');
echo output_helper::nav('help', $courseid > 0 ? $courseid : null);

echo html_writer::div(get_string('quickhelp', 'local_edc_exporter'), 'edc-title');
echo html_writer::div(get_string('quickhelp_desc', 'local_edc_exporter'), 'edc-subtitle');

$sections = [
    'help_setup_title' => [
        'help_setup_issuer',
        'help_setup_fields',
        'help_setup_course',
        'help_setup_generate',
    ],
    'help_required_title' => [
        'help_required_outcomes',
        'help_required_workload',
    ],
    'help_visual_title' => [
        'help_visual_default',
        'help_visual_html',
        'help_visual_image',
    ],
    'help_validation_title' => [
        'help_validation_internal',
        'help_validation_external',
    ],
];

echo html_writer::start_div('edc-help-grid');
foreach ($sections as $titlekey => $itemkeys) {
    echo html_writer::start_div('edc-help-section');
    echo html_writer::tag('h3', get_string($titlekey, 'local_edc_exporter'));
    echo html_writer::start_tag('ul');
    foreach ($itemkeys as $itemkey) {
        echo html_writer::tag('li', get_string($itemkey, 'local_edc_exporter'));
    }
    echo html_writer::end_tag('ul');
    echo html_writer::end_div();
}
echo html_writer::end_div();

echo html_writer::end_div();
echo $OUTPUT->footer();
