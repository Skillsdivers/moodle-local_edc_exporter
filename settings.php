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
 * Admin settings page for the EDC exporter plugin.
 *
 * @package    local_edc_exporter
 * @copyright  2026 Skills Divers
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if (!class_exists('local_edc_exporter_admin_setting_html')) {
    /**
     * Read-only admin setting used to render custom HTML blocks.
     *
     * @package    local_edc_exporter
     * @copyright  2026 Skills Divers
     * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     */
    class local_edc_exporter_admin_setting_html extends admin_setting {
        /**
         * HTML content rendered by the setting.
         *
         * @var string
         */
        private string $html;

        /**
         * Creates a read-only HTML admin setting.
         *
         * @param string $name Setting name.
         * @param string $visiblename Visible heading.
         * @param string $html HTML content.
         */
        public function __construct(string $name, string $visiblename, string $html) {
            parent::__construct($name, $visiblename, '', '');
            $this->html = $html;
        }

        /**
         * Returns a fixed value because this setting is display-only.
         *
         * @return bool
         */
        public function get_setting(): bool {
            return true;
        }

        /**
         * Does not write any value because this setting is display-only.
         *
         * @param mixed $data Submitted data.
         * @return string Empty string means no error.
         */
        public function write_setting($data): string {
            return '';
        }

        /**
         * Outputs the configured HTML block.
         *
         * @param mixed $data Current data.
         * @param string $query Search query.
         * @return string Rendered setting HTML.
         */
        public function output_html($data, $query = ''): string {
            return format_admin_setting(
                $this,
                $this->visiblename,
                $this->html,
                '',
                false,
                '',
                null,
                $query
            );
        }
    }
}

if ($hassiteconfig) {
    // Only site administrators can view this settings page.
    // Moodle stores these values and export_service reads them when generating credentials.
    $settings = new admin_settingpage('local_edc_exporter', get_string('pluginname', 'local_edc_exporter'));
    $ADMIN->add('localplugins', $settings);

    $setupurl = \local_edc_exporter\local\ui\output_helper::setup_url();

    $guidehtml = $OUTPUT->render_from_template('local_edc_exporter/settings_intro', [
        'navigation' => \local_edc_exporter\local\ui\output_helper::nav('settings'),
        'heading' => get_string('settingsintroheading', 'local_edc_exporter'),
        'description' => get_string('settingsintrodesc', 'local_edc_exporter'),
        'setupurl' => $setupurl->out(false),
        'setuplabel' => get_string('opensetupassistant', 'local_edc_exporter'),
    ]);

    $settings->add(new local_edc_exporter_admin_setting_html(
        'local_edc_exporter/setupguide',
        get_string('setupguideheading', 'local_edc_exporter'),
        $guidehtml
    ));

    $settings->add(new admin_setting_heading(
        'local_edc_exporter/issuerheading',
        get_string('issuerheading_numbered', 'local_edc_exporter'),
        get_string('issuerheadingdesc', 'local_edc_exporter')
    ));

    $settings->add(new admin_setting_configtext(
        'local_edc_exporter/awarding_body_legal_name',
        get_string('awardingbodylegalname', 'local_edc_exporter'),
        get_string('awardingbodylegalname_desc', 'local_edc_exporter'),
        get_site()->fullname,
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_edc_exporter/awarding_body_country',
        get_string('awardingbodycountry', 'local_edc_exporter'),
        get_string('awardingbodycountry_desc', 'local_edc_exporter'),
        '',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_edc_exporter/awarding_body_country_label',
        get_string('awardingbodycountrylabel', 'local_edc_exporter'),
        get_string('awardingbodycountrylabel_desc', 'local_edc_exporter'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_edc_exporter/awarding_body_homepage',
        get_string('awardingbodyhomepage', 'local_edc_exporter'),
        get_string('awardingbodyhomepage_desc', 'local_edc_exporter'),
        '',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'local_edc_exporter/awarding_body_identifier',
        get_string('awardingbodyidentifier', 'local_edc_exporter'),
        get_string('awardingbodyidentifier_desc', 'local_edc_exporter'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configstoredfile(
        'local_edc_exporter/issuerlogo',
        get_string('issuerlogo', 'local_edc_exporter'),
        get_string('issuerlogo_desc', 'local_edc_exporter'),
        'issuerlogo',
        0,
        [
            // Only one active issuer logo is allowed.
            'maxfiles' => 1,
            // Limit uploads to 2 MB to avoid excessively large files.
            'maxbytes' => 2 * 1024 * 1024,
            // SVG is not allowed at this stage to avoid security risks.
            'accepted_types' => ['.png', '.jpg', '.jpeg'],
        ]
    ));

    $settings->add(new admin_setting_heading(
        'local_edc_exporter/displayheading',
        get_string('displayheading_numbered', 'local_edc_exporter'),
        get_string('displayheadingdesc', 'local_edc_exporter')
    ));

    // -----------------------------------------------------------------------------
    // 2. Credential visual design.
    // -----------------------------------------------------------------------------
    $settings->add(new admin_setting_heading(
        'local_edc_exporter/displayheadingdetails',
        get_string('displayheading', 'local_edc_exporter'),
        get_string('displayheading_desc', 'local_edc_exporter')
    ));

    $settings->add(new admin_setting_configselect(
        'local_edc_exporter/display_mode',
        get_string('displaymode', 'local_edc_exporter'),
        get_string('displaymode_desc', 'local_edc_exporter'),
        \local_edc_exporter\local\credential\display_template_service::MODE_TEMPLATE_BUILDER,
        [
            \local_edc_exporter\local\credential\display_template_service::MODE_TEMPLATE_BUILDER =>
                get_string('displaymode_templatebuilder', 'local_edc_exporter'),
            \local_edc_exporter\local\credential\display_template_service::MODE_CUSTOM_HTML =>
                get_string('displaymode_customhtml', 'local_edc_exporter'),
        ]
    ));

    // Template builder: header image.
    $settings->add(new \local_edc_exporter\local\admin_setting_image(
        'local_edc_exporter/display_template_header',
        get_string('displaytemplateheader', 'local_edc_exporter'),
        get_string('displaytemplateheader_desc', 'local_edc_exporter'),
        'display_template_header',
        0,
        [
            'maxfiles' => 1,
            'accepted_types' => ['.png', '.jpg', '.jpeg'],
        ],
        794,
        160
    ));

    // Template builder: footer image.
    $settings->add(new \local_edc_exporter\local\admin_setting_image(
        'local_edc_exporter/display_template_footer',
        get_string('displaytemplatefooter', 'local_edc_exporter'),
        get_string('displaytemplatefooter_desc', 'local_edc_exporter'),
        'display_template_footer',
        0,
        [
            'maxfiles' => 1,
            'accepted_types' => ['.png', '.jpg', '.jpeg'],
        ],
        794,
        120
    ));

    // Template builder: background selector.
    // The custom colour option shows an additional field where the admin writes a hex colour.
    $settings->add(new admin_setting_configselect(
        'local_edc_exporter/display_template_background',
        get_string('displaytemplatebackground', 'local_edc_exporter'),
        get_string('displaytemplatebackground_desc', 'local_edc_exporter'),
        'light',
        [
            'light' => get_string('displaytemplatebackground_light', 'local_edc_exporter'),
            'green' => get_string('displaytemplatebackground_green', 'local_edc_exporter'),
            'blue' => get_string('displaytemplatebackground_blue', 'local_edc_exporter'),
            'customcolor' => get_string('displaytemplatebackground_customcolor', 'local_edc_exporter'),
        ]
    ));

    // Template builder: custom background colour.
    // This field is only used when the custom colour option is selected above.
    $settings->add(new admin_setting_configtext(
        'local_edc_exporter/display_template_background_customcolor',
        get_string('displaytemplatebackgroundcustomcolor', 'local_edc_exporter'),
        get_string('displaytemplatebackgroundcustomcolor_desc', 'local_edc_exporter'),
        '#ffffff',
        PARAM_TEXT
    ));

    // Custom HTML editor.
    // Empty by default: the administrator writes or pastes their own HTML template.
    $settings->add(new admin_setting_configtextarea(
        'local_edc_exporter/display_custom_html',
        get_string('displaycustomhtml', 'local_edc_exporter'),
        get_string('displaycustomhtml_desc', 'local_edc_exporter'),
        '',
        PARAM_RAW
    ));

    // Dynamic visibility for the design settings.
    // Template builder shows only guided options.
    // Custom HTML shows only the HTML field.
    $PAGE->requires->js_call_amd('local_edc_exporter/display_settings', 'init');

    // Guide to the variables available for the custom HTML template.
    // This section is informational only and does not store configuration.
    $customhtmlvariableshtml = $OUTPUT->render_from_template('local_edc_exporter/custom_html_variables', [
        'heading' => get_string('customhtmlvariablesheading', 'local_edc_exporter'),
        'description' => get_string('customhtmlvariables_desc', 'local_edc_exporter'),
        'variableheading' => get_string('customhtmlvariables_variable', 'local_edc_exporter'),
        'dataheading' => get_string('customhtmlvariables_displayeddata', 'local_edc_exporter'),
        'variables' => [
            ['placeholder' => '{{learner_name}}',
                'description' => get_string('customhtmlvariables_learnername', 'local_edc_exporter')],
            ['placeholder' => '{{course_name}}',
                'description' => get_string('customhtmlvariables_coursename', 'local_edc_exporter')],
            ['placeholder' => '{{credential_title}}',
                'description' => get_string('customhtmlvariables_credentialtitle', 'local_edc_exporter')],
            ['placeholder' => '{{issuer_name}}',
                'description' => get_string('customhtmlvariables_issuername', 'local_edc_exporter')],
            ['placeholder' => '{{issue_date}}',
                'description' => get_string('customhtmlvariables_issuedate', 'local_edc_exporter')],
            ['placeholder' => '{{eqf_level}}',
                'description' => get_string('customhtmlvariables_eqflevel', 'local_edc_exporter')],
            ['placeholder' => '{{workload}}',
                'description' => get_string('customhtmlvariables_workload', 'local_edc_exporter')],
            ['placeholder' => '{{learning_outcomes}}',
                'description' => get_string('customhtmlvariables_learningoutcomes', 'local_edc_exporter')],
            ['placeholder' => '{{verification_url}}',
                'description' => get_string('customhtmlvariables_verificationurl', 'local_edc_exporter')],
            ['placeholder' => '{{issuer_logo}}',
                'description' => get_string('customhtmlvariables_issuerlogo', 'local_edc_exporter')],
        ],
        'warning' => get_string('customhtmlvariables_warning', 'local_edc_exporter'),
    ]);

    $settings->add(new local_edc_exporter_admin_setting_html(
        'local_edc_exporter/customhtmlvariablesguide',
        get_string('customhtmlvariablesheading', 'local_edc_exporter'),
        $customhtmlvariableshtml
    ));
}
