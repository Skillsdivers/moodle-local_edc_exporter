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
 * Credential file management helpers.
 *
 * @package    local_edc_exporter
 * @copyright  2026 Skills Divers
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edc_exporter\local\credential;


/**
 * Handles credential JSON paths and file-system operations.
 */
class credential_file_manager {
    /**
     * Converts a stored relative path into an absolute path inside moodledata.
     *
     * @param string $relativepath Relative path stored in the database.
     * @return string Absolute path under moodledata.
     */
    public static function build_full_path(string $relativepath): string {
        $relativepath = str_replace('\\', '/', $relativepath);
        $relativepath = ltrim($relativepath, '/');

        $segments = explode('/', $relativepath);

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \moodle_exception('invalidparameter');
            }
        }

        return self::get_credentials_base_dir() . '/' . $relativepath;
    }

    /**
     * Builds the relative path for each generated credential JSON file.
     *
     * @param int $courseid Course ID.
     * @param int $userid User ID.
     * @param string $credentialid Credential identifier.
     * @param string $type File type.
     * @return string Relative JSON path.
     */
    public static function build_relative_path(int $courseid, int $userid, string $credentialid, string $type): string {
        // Keep all credential JSON files grouped by course and user.
        $basepath = $courseid . '/' . $userid . '/' . $credentialid;

        return match ($type) {
            'export' => $basepath . '.json',
            'internal' => $basepath . '.internal.json',
            'compat_export' => $basepath . '.export.json',
            default => $basepath . '.' . $type . '.json',
        };
    }

    /**
     * Ensures that the target directory exists.
     *
     * @param string $directory Directory that must exist.
     * @return void
     */
    public static function ensure_directory(string $directory): void {
        global $CFG;

        if (!is_dir($directory)) {
            mkdir($directory, $CFG->directorypermissions ?? 0770, true);
        }
    }

    /**
     * Deletes previous JSON files linked to a credential record.
     *
     * @param object $record Record from local_edc_exporter_cred.
     * @return void
     */
    public static function delete_existing_credential_files(object $record): void {
        $paths = [];

        if (!empty($record->export_json_path)) {
            $paths[] = self::build_full_path($record->export_json_path);
        }

        if (!empty($record->internal_json_path)) {
            $paths[] = self::build_full_path($record->internal_json_path);
        }

        if (!empty($record->courseid) && !empty($record->userid)) {
            $userfolder = self::build_full_path((int) $record->courseid . '/' . (int) $record->userid);

            if (is_dir($userfolder)) {
                foreach (glob($userfolder . '/*.json') ?: [] as $filepath) {
                    $paths[] = $filepath;
                }
            }
        }

        $basedir = realpath(self::get_credentials_base_dir());

        if ($basedir === false) {
            return;
        }

        foreach (array_unique($paths) as $filepath) {
            $realpath = realpath($filepath);

            if ($realpath !== false && strpos($realpath, $basedir . DIRECTORY_SEPARATOR) === 0 && is_file($realpath)) {
                unlink($realpath);
            }
        }
    }

    /**
     * Returns the base directory where credential JSON files are stored.
     *
     * @return string Absolute credential storage directory.
     */
    private static function get_credentials_base_dir(): string {
        global $CFG;

        return rtrim($CFG->dataroot, '/') . '/local_edc_exporter/credentials';
    }
}
