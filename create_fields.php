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
 * Creates the recommended course custom fields used by the EDC exporter plugin.
 *
 * @package    local_edc_exporter
 * @copyright  2026 Skills Divers
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_edc_exporter\local\setup\field_definition;

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$categoryname = get_string('customfieldcategoryname', 'local_edc_exporter');

$category = $DB->get_record('customfield_category', [
    'name' => $categoryname,
    'component' => 'core_course',
    'area' => 'course',
]);

if (!$category) {
    $category = new stdClass();
    $category->name = $categoryname;
    $category->component = 'core_course';
    $category->area = 'course';
    $category->itemid = 0;
    $category->contextid = $context->id;
    $category->timecreated = time();
    $category->timemodified = time();
    $category->sortorder = 0;

    $category->id = $DB->insert_record('customfield_category', $category);
}

foreach (field_definition::get_fields() as $field) {
    $shortname = $field['defaultsource'];

    $exists = $DB->record_exists('customfield_field', [
        'shortname' => $shortname,
        'categoryid' => $category->id,
    ]);

    if ($exists) {
        continue;
    }

    $record = new stdClass();
    $record->shortname = $shortname;
    $record->name = $field['label'];

    $fieldtype = 'text';
    $configdata = [
        'required' => '0',
        'uniquevalues' => '0',
        'locked' => '0',
        'visibility' => '2',
    ];

    switch ($shortname) {
        case 'credential_description_es':
        case 'credential_description_en':
        case 'credential_learning_outcomes':
            $fieldtype = 'textarea';
            $configdata['defaultvalue'] = '';
            $configdata['defaultvalueformat'] = '1';
            break;

        case 'credential_workload_hours':
            $fieldtype = 'number';
            $configdata['fieldtype'] = '';
            $configdata['activitytypes'] = [];
            $configdata['defaultvalue'] = '';
            $configdata['minimumvalue'] = '';
            $configdata['maximumvalue'] = '';
            $configdata['decimalplaces'] = 0;
            $configdata['display'] = '{value}';
            $configdata['displaywhenzero'] = '0';
            break;

        case 'credential_modality':
            $fieldtype = 'select';
            $configdata['options'] = "Online\r\nBlended\r\nOnsite";
            $configdata['defaultvalue'] = '';
            break;

        case 'credential_eqf_level':
            $fieldtype = 'select';
            $configdata['options'] = "1\r\n2\r\n3\r\n4\r\n5\r\n6\r\n7\r\n8";
            $configdata['defaultvalue'] = '';
            break;

        case 'credential_assessment_type':
            $fieldtype = 'select';
            $configdata['options'] = "Final graded assessment\r\nQuiz\r\nPractical assignment\r\nProject\r\nContinuous assessment";
            $configdata['defaultvalue'] = '';
            break;
    }

    $record->type = $fieldtype;
    $record->description = '';
    $record->descriptionformat = FORMAT_HTML;
    $record->categoryid = $category->id;
    $record->configdata = json_encode($configdata);

    $record->timecreated = time();
    $record->timemodified = time();
    $record->sortorder = 0;

    $DB->insert_record('customfield_field', $record);
}

redirect(
    new moodle_url('/admin/settings.php', ['section' => 'local_edc_exporter']),
    get_string('recommendedfieldscreated', 'local_edc_exporter'),
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
