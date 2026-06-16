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

namespace local_edc_exporter\local\ui;

/**
 * Helper for URLs, links, and CSS classes used by plugin screens.
 *
 * It does not decide permissions or generate credentials. It only avoids
 * duplicated presentation code in index.php and view.php.
 *
 * @package    local_edc_exporter
 * @copyright  2026 Skills Divers
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class output_helper {
    /** Status used when a credential was generated successfully. */
    public const STATUS_GENERATED = 'generated';

    /** Status used when generation failed. */
    public const STATUS_ERROR = 'error';

    /** Status used while a credential is being generated. */
    public const STATUS_PROCESSING = 'processing';

    /** Revoked credential status. */
    public const STATUS_REVOKED = 'revoked';

    /**
     * Returns the plugin CSS URL.
     *
     * @return \moodle_url URL for styles.css.
     */
    public static function css_url(): \moodle_url {
        return new \moodle_url('/local/edc_exporter/styles.css');
    }

    /**
     * Builds the credential list URL for a course.
     *
     * @param int $courseid Course ID.
     * @return \moodle_url URL for index.php with courseid.
     */
    public static function list_url(int $courseid): \moodle_url {
        return new \moodle_url('/local/edc_exporter/index.php', ['courseid' => $courseid]);
    }

    /**
     * Builds the visual credential view URL.
     *
     * @param int $id local_edc_exporter_cred record ID.
     * @return \moodle_url URL for view.php with id.
     */
    public static function view_url(int $id): \moodle_url {
        return new \moodle_url('/local/edc_exporter/view.php', ['id' => $id]);
    }

    /**
     * Builds the public verification URL for a credential token.
     *
     * @param string $token Public verification token.
     * @return \moodle_url URL of verify.php with token.
     */
    public static function verify_url(string $token): \moodle_url {
        return new \moodle_url('/local/edc_exporter/verify.php', ['token' => $token]);
    }

    /**
     * Builds a sesskey-protected download URL.
     *
     * @param int $id local_edc_exporter_cred record ID.
     * @param string $type Download type: export or internal.
     * @return \moodle_url URL for download.php.
     */
    public static function download_url(int $id, string $type): \moodle_url {
        // Sesskey helps Moodle confirm the action comes from the current session.
        return new \moodle_url('/local/edc_exporter/download.php', [
            'id' => $id,
            'type' => $type,
            'sesskey' => sesskey(),
        ]);
    }

    /**
     * Builds the URL to regenerate a credential.
     *
     * @param int $id local_edc_exporter_cred record ID.
     * @return \moodle_url URL for regenerate.php.
     */
    public static function regenerate_url(int $id): \moodle_url {
        return new \moodle_url('/local/edc_exporter/regenerate.php', [
            'id' => $id,
            'sesskey' => sesskey(),
        ]);
    }

    /**
     * Builds the credential revocation form URL.
     *
     * @param int $id local_edc_exporter_cred record ID.
     * @return \moodle_url URL for revoke.php.
     */
    public static function revoke_url(int $id): \moodle_url {
        return new \moodle_url('/local/edc_exporter/revoke.php', ['id' => $id]);
    }

    /**
     * Builds the URL to generate pending credentials for a course.
     *
     * @param int $courseid Course ID.
     * @return \moodle_url URL for generate_pending.php.
     */
    public static function generate_pending_url(int $courseid): \moodle_url {
        return new \moodle_url('/local/edc_exporter/generate_pending.php', [
            'courseid' => $courseid,
            'sesskey' => sesskey(),
        ]);
    }

    /**
     * Builds the setup assistant URL.
     *
     * @param int|null $courseid Optional course context.
     * @return \moodle_url Setup URL.
     */
    public static function setup_url(?int $courseid = null): \moodle_url {
        $params = [];
        if ($courseid !== null) {
            $params['courseid'] = $courseid;
        }
        return new \moodle_url('/local/edc_exporter/setup.php', $params);
    }

    /**
     * Builds the EDC fields reference URL.
     *
     * @return \moodle_url Fields URL.
     */
    public static function fields_url(): \moodle_url {
        return new \moodle_url('/local/edc_exporter/fields.php');
    }

    /**
     * Builds the quick help URL.
     *
     * @param int|null $courseid Optional course context.
     * @return \moodle_url Help URL.
     */
    public static function help_url(?int $courseid = null): \moodle_url {
        $params = [];
        if ($courseid !== null) {
            $params['courseid'] = $courseid;
        }
        return new \moodle_url('/local/edc_exporter/help.php', $params);
    }

    /**
     * Renders the plugin navigation bar.
     *
     * @param string $active Active item key.
     * @param int|null $courseid Optional course context.
     * @return string HTML navigation.
     */
    public static function nav(string $active, ?int $courseid = null): string {
        $items = [
            'summary' => [self::setup_url($courseid), get_string('nav_summary', 'local_edc_exporter')],
        ];

        if (is_siteadmin()) {
            $items['settings'] = [
                new \moodle_url('/admin/settings.php', ['section' => 'local_edc_exporter']),
                get_string('nav_settings', 'local_edc_exporter'),
            ];
            $items['fields'] = [self::fields_url(), get_string('nav_fields', 'local_edc_exporter')];
        }

        $items['help'] = [self::help_url($courseid), get_string('nav_help', 'local_edc_exporter')];

        $links = [];
        foreach ($items as $key => [$url, $label]) {
            $class = 'edc-nav-link';
            if ($key === $active) {
                $class .= ' edc-nav-active';
            }
            $links[] = \html_writer::link($url, $label, ['class' => $class]);
        }

        return \html_writer::tag('nav', implode('', $links), ['class' => 'edc-nav']);
    }

    /**
     * Returns the CSS class for a credential status.
     *
     * @param string $status Status stored in the database.
     * @return string CSS classes for the status label.
     */
    public static function status_class(string $status): string {
        // Generated is displayed as successful.
        if ($status === self::STATUS_GENERATED) {
            return 'edc-status edc-status-generated';
        }
        if ($status === self::STATUS_ERROR) {
            // Error is displayed as a failure.
            return 'edc-status edc-status-error';
        }
        if ($status === self::STATUS_REVOKED) {
            return 'edc-status edc-status-revoked';
        }
        // Any other status is displayed as pending or processing.
        return 'edc-status edc-status-processing';
    }

    /**
     * Creates a link using the plugin button style.
     *
     * @param \moodle_url $url Destination URL.
     * @param string $label Visible link text.
     * @param string $class Additional CSS class.
     * @param array $attributes Extra HTML attributes, such as title or onclick.
     * @return string Link HTML.
     */
    public static function action_link(
        \moodle_url $url,
        string $label,
        string $class = 'edc-btn-primary',
        array $attributes = []
    ): string {
        $attributes = array_merge(['class' => 'edc-btn ' . $class], $attributes);
        return \html_writer::link($url, $label, $attributes);
    }

    /**
     * Returns the public URL for the configured institutional logo.
     *
     * The file is retrieved through the Moodle File API. If no logo is configured,
     * null is returned so screens can hide the block.
     *
     * @return \moodle_url|null Logo URL or null when it does not exist.
     */
    public static function issuer_logo_url(): ?\moodle_url {
        $context = \context_system::instance();
        $fs = get_file_storage();

        $files = $fs->get_area_files(
            $context->id,
            'local_edc_exporter',
            'issuerlogo',
            0,
            'filename',
            false
        );

        if (empty($files)) {
            return null;
        }

        $file = reset($files);

        if (!$file || $file->is_directory()) {
            return null;
        }

        return \moodle_url::make_pluginfile_url(
            $context->id,
            'local_edc_exporter',
            'issuerlogo',
            0,
            $file->get_filepath(),
            $file->get_filename(),
            false
        );
    }
}
