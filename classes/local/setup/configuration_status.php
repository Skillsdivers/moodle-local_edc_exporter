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

namespace local_edc_exporter\local\setup;

use local_edc_exporter\local\credential\course_metadata;
use local_edc_exporter\local\credential\display_template_service;

/**
 * Centralises setup diagnostics for the EDC exporter.
 *
 * @package    local_edc_exporter
 * @copyright  2026 Skills Divers
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class configuration_status {
    /** Critical missing configuration: generation must be blocked. */
    public const SEVERITY_ERROR = 'error';

    /** Non-critical missing configuration: generation can continue. */
    public const SEVERITY_WARNING = 'warning';

    /** Requirement is satisfied. */
    public const SEVERITY_SUCCESS = 'success';

    /**
     * Builds global plugin setup status.
     *
     * @return array
     */
    public static function global_status(): array {
        $items = array_merge(
            self::issuer_items(),
            self::field_definition_items(),
            self::display_items(),
            self::permission_items()
        );

        return self::build_status($items);
    }

    /**
     * Builds setup status for a specific course.
     *
     * @param int $courseid Course id.
     * @return array
     */
    public static function course_status(int $courseid): array {
        $items = array_merge(
            self::global_status()['items'],
            self::course_metadata_items($courseid)
        );

        return self::build_status($items);
    }

    /**
     * Throws if a course is not ready for generation.
     *
     * @param int $courseid Course id.
     * @return void
     */
    public static function require_course_ready(int $courseid): void {
        $status = self::course_status($courseid);

        if (!$status['ready']) {
            throw new \moodle_exception(
                'configurationnotready',
                'local_edc_exporter',
                '',
                self::summary_text($status)
            );
        }
    }

    /**
     * Returns a short readable summary for notifications and exceptions.
     *
     * @param array $status Status returned by this class.
     * @return string
     */
    public static function summary_text(array $status): string {
        if (!empty($status['ready'])) {
            return get_string('setupstatus_ready', 'local_edc_exporter');
        }

        $labels = [];
        foreach ($status['items'] as $item) {
            if (($item['severity'] ?? '') === self::SEVERITY_ERROR) {
                $labels[] = $item['title'];
            }
        }

        return get_string('setupstatus_blocked', 'local_edc_exporter') . ': ' . implode(', ', $labels);
    }

    /**
     * Returns the critical items that block credential generation.
     *
     * @param array $status Status returned by this class.
     * @return array Blocking diagnostic items.
     */
    public static function blocking_items(array $status): array {
        $items = [];

        foreach ($status['items'] as $item) {
            if (($item['severity'] ?? '') === self::SEVERITY_ERROR) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Renders a status panel for admin and course pages.
     *
     * Kept as a compatibility wrapper so existing pages can still call
     * configuration_status::render_panel().
     *
     * @param array $status Status returned by this class.
     * @param bool $showactions Whether to include action links.
     * @return string HTML.
     */
    public static function render_panel(array $status, bool $showactions = true): string {
        return setup_renderer::render_panel($status, $showactions);
    }

    /**
     * Builds the standard status envelope.
     *
     * @param array $items Diagnostic items.
     * @return array
     */
    private static function build_status(array $items): array {
        $errors = 0;
        $warnings = 0;

        foreach ($items as $item) {
            if ($item['severity'] === self::SEVERITY_ERROR) {
                $errors++;
            } else if ($item['severity'] === self::SEVERITY_WARNING) {
                $warnings++;
            }
        }

        return [
            'ready' => $errors === 0,
            'errorcount' => $errors,
            'warningcount' => $warnings,
            'title' => $errors === 0
                ? get_string('setupstatus_ready_title', 'local_edc_exporter')
                : get_string('setupstatus_blocked_title', 'local_edc_exporter'),
            'summary' => $errors === 0
                ? get_string('setupstatus_ready_desc', 'local_edc_exporter')
                : get_string('setupstatus_blocked_desc', 'local_edc_exporter'),
            'items' => $items,
        ];
    }

    /**
     * Checks issuer settings.
     *
     * @return array
     */
    private static function issuer_items(): array {
        $legalname = trim((string) get_config('local_edc_exporter', 'awarding_body_legal_name'));
        $country = trim((string) get_config('local_edc_exporter', 'awarding_body_country'));
        $countrylabel = trim((string) get_config('local_edc_exporter', 'awarding_body_country_label'));

        $missing = [];
        if ($legalname === '') {
            $missing[] = get_string('awardingbodylegalname', 'local_edc_exporter');
        }
        if ($country === '') {
            $missing[] = get_string('awardingbodycountry', 'local_edc_exporter');
        }
        if ($countrylabel === '') {
            $missing[] = get_string('awardingbodycountrylabel', 'local_edc_exporter');
        }

        return [[
            'key' => 'issuer',
            'title' => get_string('setupcheck_issuer', 'local_edc_exporter'),
            'message' => empty($missing)
                ? get_string('setupcheck_issuer_ok', 'local_edc_exporter')
                : get_string('setupcheck_missing', 'local_edc_exporter', implode(', ', $missing)),
            'severity' => empty($missing) ? self::SEVERITY_SUCCESS : self::SEVERITY_ERROR,
            'actionurl' => new \moodle_url('/admin/settings.php', ['section' => 'local_edc_exporter']),
            'actionlabel' => get_string('pluginsettings', 'local_edc_exporter'),
        ]];
    }

    /**
     * Checks required custom field definitions.
     *
     * @return array
     */
    private static function field_definition_items(): array {
        global $DB;

        $missing = [];
        foreach (field_definition::get_fields() as $field) {
            if (empty($field['required'])) {
                continue;
            }

            if (!$DB->record_exists('customfield_field', ['shortname' => $field['defaultsource']])) {
                $missing[] = $field['defaultsource'];
            }
        }

        return [[
            'key' => 'fielddefinitions',
            'title' => get_string('setupcheck_fields', 'local_edc_exporter'),
            'message' => empty($missing)
                ? get_string('setupcheck_fields_ok', 'local_edc_exporter')
                : get_string('setupcheck_missing', 'local_edc_exporter', implode(', ', $missing)),
            'severity' => empty($missing) ? self::SEVERITY_SUCCESS : self::SEVERITY_ERROR,
            'actionurl' => new \moodle_url('/local/edc_exporter/create_fields.php', ['sesskey' => sesskey()]),
            'actionlabel' => get_string('createrecommendedfields', 'local_edc_exporter'),
        ]];
    }

    /**
     * Checks optional display configuration.
     *
     * @return array
     */
    private static function display_items(): array {
        $mode = trim((string) get_config('local_edc_exporter', 'display_mode'));
        if ($mode === '') {
            $mode = display_template_service::MODE_DEFAULT;
        }

        $message = get_string('setupcheck_display_default', 'local_edc_exporter');
        $severity = self::SEVERITY_SUCCESS;

        if ($mode === display_template_service::MODE_CUSTOM_HTML) {
            $customhtml = trim((string) get_config('local_edc_exporter', 'display_custom_html'));
            if ($customhtml === '') {
                $message = get_string('setupcheck_display_custom_missing', 'local_edc_exporter');
                $severity = self::SEVERITY_WARNING;
            } else {
                $message = get_string('setupcheck_display_custom_ok', 'local_edc_exporter');
            }
        } else if ($mode === display_template_service::MODE_BACKGROUND_IMAGE) {
            $message = self::has_stored_file('displaybackground')
                ? get_string('setupcheck_display_image_ok', 'local_edc_exporter')
                : get_string('setupcheck_display_image_missing', 'local_edc_exporter');
            $severity = self::has_stored_file('displaybackground') ? self::SEVERITY_SUCCESS : self::SEVERITY_WARNING;
        }

        return [[
            'key' => 'display',
            'title' => get_string('setupcheck_display', 'local_edc_exporter'),
            'message' => $message,
            'severity' => $severity,
            'actionurl' => new \moodle_url('/admin/settings.php', ['section' => 'local_edc_exporter']),
            'actionlabel' => get_string('displaymode', 'local_edc_exporter'),
        ]];
    }

    /**
     * Adds a permissions readiness item.
     *
     * @return array
     */
    private static function permission_items(): array {
        return [[
            'key' => 'permissions',
            'title' => get_string('setupcheck_permissions', 'local_edc_exporter'),
            'message' => get_string('setupcheck_permissions_ok', 'local_edc_exporter'),
            'severity' => self::SEVERITY_SUCCESS,
            'actionurl' => new \moodle_url('/admin/roles/manage.php'),
            'actionlabel' => get_string('setupcheck_permissions_action', 'local_edc_exporter'),
        ]];
    }

    /**
     * Checks required course metadata values.
     *
     * @param int $courseid Course id.
     * @return array
     */
    private static function course_metadata_items(int $courseid): array {
        $metadata = course_metadata::get_course_custom_fields($courseid);
        $items = [];

        foreach (field_definition::get_fields() as $field) {
            if (empty($field['required'])) {
                continue;
            }

            $sources = [$field['defaultsource']];
            if (!empty($field['fallbacksources']) && is_array($field['fallbacksources'])) {
                $sources = array_merge($sources, $field['fallbacksources']);
            }

            $found = false;
            foreach ($sources as $source) {
                if (trim((string) ($metadata[$source] ?? '')) !== '') {
                    $found = true;
                    break;
                }
            }

            $items[] = [
                'key' => 'course_' . $field['defaultsource'],
                'title' => get_string('setupcheck_coursefield', 'local_edc_exporter', $field['label']),
                'message' => $found
                    ? get_string('setupcheck_coursefield_ok', 'local_edc_exporter')
                    : get_string('setupcheck_coursefield_missing', 'local_edc_exporter', implode(', ', $sources)),
                'severity' => $found ? self::SEVERITY_SUCCESS : self::SEVERITY_ERROR,
                'actionurl' => new \moodle_url('/course/edit.php', ['id' => $courseid]),
                'actionlabel' => get_string('editcourse', 'local_edc_exporter'),
            ];
        }

        return $items;
    }

    /**
     * Checks if a plugin stored file area has a file.
     *
     * @param string $filearea File area.
     * @return bool
     */
    private static function has_stored_file(string $filearea): bool {
        $context = \context_system::instance();
        $files = get_file_storage()->get_area_files(
            $context->id,
            'local_edc_exporter',
            $filearea,
            0,
            'filename',
            false
        );

        return !empty($files);
    }
}
