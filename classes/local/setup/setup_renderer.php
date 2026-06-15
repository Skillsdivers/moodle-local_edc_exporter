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
 * Setup status renderer for local_edc_exporter.
 *
 * @package    local_edc_exporter
 * @copyright  2026 Skills Divers
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edc_exporter\local\setup;


/**
 * Renders setup status panels.
 */
class setup_renderer {
    /**
     * Renders a status panel for admin and course pages.
     *
     * @param array $status Status returned by configuration_status.
     * @param bool $showactions Whether to include action links.
     * @return string HTML.
     */
    public static function render_panel(array $status, bool $showactions = true): string {
        $panelclass = !empty($status['ready']) ? 'edc-setup-ready' : 'edc-setup-blocked';

        $html = \html_writer::start_div('edc-setup-panel ' . $panelclass);
        $html .= \html_writer::start_div('edc-setup-panel-header');
        $html .= \html_writer::div(
            \html_writer::tag('h3', s($status['title'])) .
                \html_writer::tag('p', s($status['summary'])),
            'edc-setup-panel-copy'
        );
        $html .= \html_writer::span(
            s($status['ready'] ? get_string('ready', 'local_edc_exporter') : get_string('requiresattention', 'local_edc_exporter')),
            'edc-chip ' . ($status['ready'] ? 'edc-chip-success' : 'edc-chip-warning')
        );
        $html .= \html_writer::end_div();

        $html .= \html_writer::start_div('edc-checklist');
        foreach ($status['items'] as $item) {
            $html .= self::render_item($item, $showactions);
        }
        $html .= \html_writer::end_div();
        $html .= \html_writer::end_div();

        return $html;
    }

    /**
     * Renders a compact status item.
     *
     * @param array $item Status item.
     * @param bool $showactions Whether to include action links.
     * @return string HTML.
     */
    private static function render_item(array $item, bool $showactions): string {
        $severity = $item['severity'];
        $html = \html_writer::start_div('edc-check-item edc-check-' . $severity);
        $html .= \html_writer::span(self::severity_symbol($severity), 'edc-check-icon');
        $html .= \html_writer::start_div('edc-check-content');
        $html .= \html_writer::div(s($item['title']), 'edc-check-title');
        $html .= \html_writer::div(s($item['message']), 'edc-check-message');

        if (
            $showactions
            && !empty($item['actionurl'])
            && !empty($item['actionlabel'])
            && self::can_show_action($item['actionurl'])
        ) {
            $html .= \html_writer::link(
                $item['actionurl'],
                s($item['actionlabel']),
                ['class' => 'edc-check-action']
            );
        }

        $html .= \html_writer::end_div();
        $html .= \html_writer::end_div();

        return $html;
    }

    /**
     * Returns a simple status symbol.
     *
     * @param string $severity Severity.
     * @return string
     */
    private static function severity_symbol(string $severity): string {
        if ($severity === configuration_status::SEVERITY_SUCCESS) {
            return 'OK';
        }

        if ($severity === configuration_status::SEVERITY_WARNING) {
            return '!';
        }

        return 'X';
    }

    /**
     * Avoids showing site administration actions to users who cannot use them.
     *
     * @param \moodle_url $url Action URL.
     * @return bool
     */
    private static function can_show_action(\moodle_url $url): bool {
        $path = $url->get_path();

        if (strpos($path, '/admin/') === 0) {
            return is_siteadmin();
        }

        if (strpos($path, '/local/edc_exporter/create_fields.php') === 0) {
            return is_siteadmin();
        }

        return true;
    }
}
