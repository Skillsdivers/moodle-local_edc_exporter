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

namespace local_edc_exporter\local\credential;

/**
 * Reads course custom fields that are later used in the credential.
 *
 * Moodle stores these fields in generic tables. This class converts them into
 * a simple array where the key is the field shortname and the value is what was entered in Moodle.
 *
 * @package    local_edc_exporter
 * @copyright  2026 Skills Divers
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_metadata {
    /**
     * Returns custom fields for a course.
     *
     * @param int $courseid Course ID.
     * @return array Map of shortname => custom field value.
     */
    public static function get_course_custom_fields(int $courseid): array {
        global $DB;

        // Queries custom field values associated with this course.
        // f.shortname is the shortname later used by jsonld_builder.
        $sql = "SELECT f.shortname, d.value
                  FROM {customfield_data} d
                  JOIN {customfield_field} f ON f.id = d.fieldid
                 WHERE d.instanceid = :courseid";

        // Records found in Moodle for the course custom fields.
        $records = $DB->get_records_sql($sql, ['courseid' => $courseid]);

        // Final array returned to the generation service.
        $fields = [];

        foreach ($records as $record) {
            // Stores the value as Moodle provides it. Cleaning happens later depending on usage.
            $fields[$record->shortname] = (string) $record->value;
        }

        return $fields;
    }
}
