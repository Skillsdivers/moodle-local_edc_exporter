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
 * Library callbacks for the EDC exporter plugin.
 *
 * @package    local_edc_exporter
 * @copyright  2026 Skills Divers
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Extends the course navigation with a link to the EDC credentials page.
 *
 * @param navigation_node $navigation Course navigation node.
 * @param stdClass $course Course record.
 * @param context_course $context Course context.
 * @return void
 */
function local_edc_exporter_extend_navigation_course($navigation, $course, $context): void {
    if (!has_capability('local/edc_exporter:view', $context)) {
        return;
    }

    $url = new moodle_url('/local/edc_exporter/index.php', ['courseid' => $course->id]);

    $navigation->add(
        get_string('pluginname', 'local_edc_exporter'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'local_edc_exporter'
    );
}

/**
 * Serves protected plugin files through the Moodle File API.
 *
 * This is currently used to display the institutional logo configured in the
 * plugin settings. The file is stored in moodledata, not as a manual path.
 *
 * @param stdClass $course Related course, if any.
 * @param stdClass $cm Related module, if any.
 * @param context $context Context where the file is requested from.
 * @param string $filearea Plugin file area.
 * @param array $args File URL arguments.
 * @param bool $forcedownload Whether download should be forced.
 * @param array $options Additional sending options.
 * @return bool False when the file does not exist or cannot be served.
 */
function local_edc_exporter_pluginfile(
    $course,
    $cm,
    $context,
    string $filearea,
    array $args,
    bool $forcedownload,
    array $options = []
): bool {
    if ($context->contextlevel !== CONTEXT_SYSTEM) {
        return false;
    }

    if ($filearea !== 'issuerlogo') {
        return false;
    }

    $itemid = array_shift($args);
    $filename = array_pop($args);

    if ((int) $itemid !== 0 || empty($filename)) {
        return false;
    }

    $filepath = '/' . implode('/', $args);
    if ($filepath === '/') {
        $filepath = '/';
    } else {
        $filepath .= '/';
    }

    $fs = get_file_storage();
    $file = $fs->get_file(
        $context->id,
        'local_edc_exporter',
        'issuerlogo',
        0,
        $filepath,
        $filename
    );

    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}
