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
 * EDC supported fields reference.
 *
 * @package    local_edc_exporter
 * @copyright  2026 Skills Divers
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_edc_exporter\local\setup\field_definition;
use local_edc_exporter\local\ui\output_helper;

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_url(output_helper::fields_url());
$PAGE->set_context($context);
$PAGE->set_title(get_string('fieldsreference', 'local_edc_exporter'));
$PAGE->set_heading(get_string('pluginname', 'local_edc_exporter'));
$PAGE->requires->css(output_helper::css_url());

echo $OUTPUT->header();
echo html_writer::start_div('edc-wrapper');
echo output_helper::nav('fields');

echo html_writer::start_div('edc-page-header');
echo html_writer::start_div();
echo html_writer::div(get_string('fieldsreference', 'local_edc_exporter'), 'edc-title');
echo html_writer::div(get_string('fieldsreference_desc', 'local_edc_exporter'), 'edc-subtitle');
echo html_writer::end_div();
echo html_writer::start_div('edc-header-actions');
echo html_writer::link(
    new moodle_url('/local/edc_exporter/create_fields.php', ['sesskey' => sesskey()]),
    get_string('createrecommendedfields', 'local_edc_exporter'),
    ['class' => 'edc-btn edc-btn-primary']
);
echo html_writer::end_div();
echo html_writer::end_div();

$fields = field_definition::get_fields();

echo html_writer::start_tag('table', ['class' => 'edc-table edc-fields-table']);
echo html_writer::start_tag('thead');
echo html_writer::tag(
    'tr',
    html_writer::tag('th', get_string('field', 'local_edc_exporter')) .
    html_writer::tag('th', get_string('required', 'local_edc_exporter')) .
    html_writer::tag('th', get_string('source', 'local_edc_exporter')) .
    html_writer::tag(
        'th',
        get_string('acceptedaliases', 'local_edc_exporter') . ' ' .
        html_writer::span('?', 'edc-help-tip', [
            'title' => get_string('aliaseshelp', 'local_edc_exporter'),
            'tabindex' => 0,
        ])
    ) .
    html_writer::tag('th', get_string('example', 'local_edc_exporter'))
);
echo html_writer::end_tag('thead');
echo html_writer::start_tag('tbody');

foreach ($fields as $field) {
    $aliases = !empty($field['fallbacksources']) ? implode(', ', $field['fallbacksources']) : '-';
    $required = !empty($field['required']);
    $chip = html_writer::span(
        get_string($required ? 'required' : 'optional', 'local_edc_exporter'),
        'edc-chip ' . ($required ? 'edc-chip-warning' : 'edc-chip-neutral')
    );

    echo html_writer::tag(
        'tr',
        html_writer::tag('td', s($field['label'])) .
        html_writer::tag('td', $chip) .
        html_writer::tag('td', html_writer::tag('code', s($field['defaultsource']))) .
        html_writer::tag('td', html_writer::tag('code', s($aliases))) .
        html_writer::tag('td', s($field['example'] ?? '-'))
    );
}

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');

echo html_writer::end_div();
echo $OUTPUT->footer();
