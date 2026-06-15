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
 * Setup assistant for the EDC exporter plugin.
 *
 * @package    local_edc_exporter
 * @copyright  2026 Skills Divers
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_edc_exporter\local\setup\configuration_status;
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

$PAGE->set_url(output_helper::setup_url($courseid > 0 ? $courseid : null));
$PAGE->set_context($context);
$PAGE->set_title(get_string('setupassistant', 'local_edc_exporter'));
$PAGE->set_heading($course ? format_string($course->fullname) : get_string('pluginname', 'local_edc_exporter'));
$PAGE->requires->css(output_helper::css_url());

echo $OUTPUT->header();

echo html_writer::start_div('edc-wrapper');
echo output_helper::nav('summary', $courseid > 0 ? $courseid : null);
echo html_writer::start_div('edc-page-header');
echo html_writer::start_div();
echo html_writer::div(get_string('setupassistant', 'local_edc_exporter'), 'edc-title');
echo html_writer::div(get_string('setupassistant_desc', 'local_edc_exporter'), 'edc-subtitle');
echo html_writer::end_div();

echo html_writer::start_div('edc-header-actions');
if (is_siteadmin()) {
    echo html_writer::link(
        new moodle_url('/admin/settings.php', ['section' => 'local_edc_exporter']),
        get_string('pluginsettings', 'local_edc_exporter'),
        ['class' => 'edc-btn edc-btn-secondary']
    );
}
if ($course) {
    echo html_writer::link(
        output_helper::list_url((int) $course->id),
        get_string('backtolist', 'local_edc_exporter'),
        ['class' => 'edc-btn edc-btn-light']
    );
}
echo html_writer::end_div();
echo html_writer::end_div();

$status = $course
    ? configuration_status::course_status((int) $course->id)
    : configuration_status::global_status();

echo configuration_status::render_panel($status, true);

echo html_writer::end_div();
echo $OUTPUT->footer();
