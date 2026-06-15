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

namespace local_edc_exporter;

use core\event\course_completed;
use local_edc_exporter\local\credential\export_service;

/**
 * Moodle event observer for the EDC Exporter plugin.
 *
 * @package    local_edc_exporter
 * @copyright  2026 Skills Divers
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Handles the course completion event.
     *
     * @param course_completed $event Event emitted by Moodle.
     * @return void
     */
    public static function course_completed(course_completed $event): void {
        try {
            export_service::handle_course_completed($event);
        } catch (\Throwable $e) {
            // Include the stack trace in developer debugging output so database,
            // permission or file-system errors can be diagnosed more easily.
            debugging(
                '[local_edc_exporter] Error generating credential: ' . $e->getMessage()
                    . "\n" . $e->getTraceAsString(),
                DEBUG_DEVELOPER
            );
        }
    }
}
