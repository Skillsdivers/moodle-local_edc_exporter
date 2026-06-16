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
 * Downloads generated EDC credential JSON files.
 *
 * @package    local_edc_exporter
 * @copyright  2026 Skills Divers
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_edc_exporter\local\audit\audit_logger;
use local_edc_exporter\local\credential\credential_file_manager;
use local_edc_exporter\local\ui\output_helper;

// Credential record ID to download.
$id = required_param('id', PARAM_INT);
// Requested file type: "export" for the official JSON or "internal" for the technical JSON.
$type = required_param('type', PARAM_ALPHANUMEXT);

// Plugin table record containing status and file paths.
$record = $DB->get_record('local_edc_exporter_cred', ['id' => $id], '*', MUST_EXIST);

// Course linked to the credential. It is used in require_login() to validate
// access inside the real course context, not only as an authenticated user.
$course = $DB->get_record('course', ['id' => $record->courseid], '*', MUST_EXIST);

require_login($course);
// Downloading changes browser state, so a sesskey is required.
require_sesskey();

// Course context used by Moodle to check permissions inside this course.
$context = context_course::instance($course->id);

// Common permission required to download course credentials.
require_capability('local/edc_exporter:download', $context);

if ($record->status === output_helper::STATUS_REVOKED) {
    throw new moodle_exception('revokedcredentialnotice', 'local_edc_exporter');
}

// Export JSON is exposed only when the credential has reached the generated status.
// Internal JSON uses a separate capability and is restricted to managers by default.
if ($type === 'export') {
    if ($record->status !== output_helper::STATUS_GENERATED) {
        throw new moodle_exception('credentialnotgenerated', 'local_edc_exporter');
    }
    // Relative path for the official JSON that can be used outside Moodle.
    $relativepath = $record->export_json_path;
} else if ($type === 'internal') {
    // Internal JSON includes Moodle technical data, so it requires a more restricted permission.
    require_capability('local/edc_exporter:downloadinternal', $context);
    $relativepath = $record->internal_json_path;
} else {
    throw new moodle_exception('invalidparameter');
}

if (empty($relativepath)) {
    throw new moodle_exception('filenotfound');
}

// Full file path inside moodledata.
$fullpath = credential_file_manager::build_full_path($relativepath);
if (!is_readable($fullpath)) {
    // The database may contain an old or deleted path; downloads do not attempt regeneration.
    throw new moodle_exception('filenotfound');
}

// Prevent any accidental previous output from corrupting the downloaded JSON.
while (ob_get_level()) {
    ob_end_clean();
}

$filename = clean_filename(basename($fullpath));
$filesize = filesize($fullpath);

if ($filesize === false) {
    throw new moodle_exception('filenotfound');
}

// Register the download before sending the file.
// Only the requested type is stored, not the credential content.
audit_logger::log(
    audit_logger::ACTION_DOWNLOADED,
    (int) $record->id,
    (int) $record->userid,
    (int) $record->courseid,
    (int) $USER->id,
    [
        'type' => $type,
    ]
);

header('Content-Type: application/ld+json; charset=utf-8');

// Provide both the traditional filename parameter and the RFC 6266 UTF-8 variant.
// This improves downloads when course names or credential IDs contain special characters.
$asciifilename = clean_filename($filename);
$utf8filename = rawurlencode($filename);

header(
    'Content-Disposition: attachment; filename="' . $asciifilename . '"; filename*=UTF-8\'\'' . $utf8filename
);

header('Content-Length: ' . $filesize);
header('X-Content-Type-Options: nosniff');

readfile($fullpath);
exit;
