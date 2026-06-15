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
 * Manual CLI runner for regenerating an EDC credential from a course completion.
 *
 * @package    local_edc_exporter
 * @copyright  2026 Skills Divers
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');

// Loads classes manually because this file runs in CLI, not as a regular web page.
require_once($CFG->dirroot . '/local/edc_exporter/classes/local/credential/course_metadata.php');
require_once($CFG->dirroot . '/local/edc_exporter/classes/local/credential/schema_validator.php');
require_once($CFG->dirroot . '/local/edc_exporter/classes/local/setup/field_definition.php');
require_once($CFG->dirroot . '/local/edc_exporter/classes/local/setup/configuration_status.php');
require_once($CFG->dirroot . '/local/edc_exporter/classes/local/credential/display_template_service.php');
require_once($CFG->dirroot . '/local/edc_exporter/classes/local/credential/jsonld_builder.php');
require_once($CFG->dirroot . '/local/edc_exporter/classes/local/credential/export_service.php');

if ($argc < 2) {
    // Shows script usage when no completion ID is provided.
    fwrite(STDERR, "Usage: php local/edc_exporter/cli/run_completion.php <completionid>\n");
    exit(1);
}

// Course_completions ID to reprocess manually.
$completionid = (int) $argv[1];

// The completion must exist; this script does not create new completions.
$completion = $DB->get_record('course_completions', ['id' => $completionid], '*', MUST_EXIST);

// Creates the same event Moodle would emit automatically when completing a course.
$event = \core\event\course_completed::create_from_completion($completion);

// Runs the normal credential generation flow.
\local_edc_exporter\local\credential\export_service::handle_course_completed($event);

mtrace('Manual credential generation run completed successfully.');
