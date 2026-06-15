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
 * Builds the visual display data included in an EDC credential.
 *
 * The JSON-LD builder owns the credential semantics. This class owns only the
 * optional presentation layer: generated cover image, custom full-page image,
 * and HTML fallback template.
 *
 * @package    local_edc_exporter
 * @copyright  2026 Skills Divers
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class display_template_service {
    /** Guided template builder with plugin-controlled visual options. */
    public const MODE_TEMPLATE_BUILDER = 'template_builder';

    /** Admin-provided HTML template. */
    public const MODE_CUSTOM_HTML = 'custom_html';

    /**
     * Legacy value kept only to avoid breaking old saved settings.
     *
     * Old installations may still have "default" stored in config.
     * It will be treated as template_builder.
     */
    public const MODE_DEFAULT = 'default';

    /**
     * Legacy value kept only to avoid breaking old saved settings.
     *
     * Old installations may still have "background_image" stored in config.
     * It will be treated as template_builder.
     */
    public const MODE_BACKGROUND_IMAGE = 'background_image';

    /** Maximum custom HTML accepted from settings. */
    private const MAX_TEMPLATE_BYTES = 200000;

    /**
     * Builds the display fragments consumed by jsonld_builder.
     *
     * @param \stdClass $record Local credential record.
     * @param string $credentialtitle Credential display title.
     * @param string $studentname Learner full name.
     * @param string $claimtitle Achievement title.
     * @param string $issuername Issuer legal name.
     * @param array $settings Plugin settings.
     * @return array{individualDisplay: array, template: string, mode: string}
     */
    public static function build(
        \stdClass $record,
        string $credentialtitle,
        string $studentname,
        string $claimtitle,
        string $issuername,
        array $settings
    ): array {
        $mode = self::normalise_mode($settings['display_mode'] ?? self::MODE_DEFAULT);
        $template = self::resolve_template(
            $mode,
            $settings,
            $record,
            $credentialtitle,
            $studentname,
            $claimtitle,
            $issuername
        );
        $coverimage = ($mode === self::MODE_CUSTOM_HTML)
            ? null
            : self::resolve_cover_image(
                $mode,
                $credentialtitle,
                $studentname,
                $claimtitle,
                $issuername,
                $settings
            );

        $individualdisplay = [];
        if ($coverimage !== null) {
            foreach (['en', 'es'] as $languagecode) {
                $individualdisplay[] = [
                    'id'   => 'urn:epass:individualDisplay:' . (int) $record->id . ':' . $languagecode,
                    'type' => 'IndividualDisplay',
                    'language' => edc_concept_helper::language_concept($languagecode),
                    'displayDetail' => [[
                        'id'    => 'urn:epass:displayDetail:' . (int) $record->id . ':' . $languagecode,
                        'type'  => 'DisplayDetail',
                        'page'  => 1,
                        'image' => $coverimage,
                    ]],
                ];
            }
        }

        return [
            'individualDisplay' => $individualdisplay,
            'template' => $template,
            'mode' => $mode,
        ];
    }

    /**
     * Normalises the display mode to a supported value.
     *
     * @param mixed $mode Raw setting.
     * @return string Supported display mode.
     */
    private static function normalise_mode($mode): string {
        $mode = (string) $mode;

        // Old saved settings are migrated logically without touching the database.
        // Both previous visual modes now use the guided template builder.
        if ($mode === self::MODE_DEFAULT || $mode === self::MODE_BACKGROUND_IMAGE) {
            return self::MODE_TEMPLATE_BUILDER;
        }

        $allowed = [
            self::MODE_TEMPLATE_BUILDER,
            self::MODE_CUSTOM_HTML,
        ];

        return in_array($mode, $allowed, true) ? $mode : self::MODE_TEMPLATE_BUILDER;
    }

    /**
     * Resolves the HTML fallback template.
     *
     * @param string $mode Display mode.
     * @param array $settings Plugin settings.
     * @param \stdClass $record Local credential record.
     * @param string $credentialtitle Credential display title.
     * @param string $studentname Learner full name.
     * @param string $claimtitle Achievement or course title.
     * @param string $issuername Issuer legal name.
     * @return string HTML template.
     */
    private static function resolve_template(
        string $mode,
        array $settings,
        \stdClass $record,
        string $credentialtitle,
        string $studentname,
        string $claimtitle,
        string $issuername
    ): string {
        if ($mode === self::MODE_CUSTOM_HTML) {
            $customhtml = trim((string) ($settings['display_custom_html'] ?? ''));

            if ($customhtml !== '' && strlen($customhtml) <= self::MAX_TEMPLATE_BYTES) {
                // The custom HTML is stored in plugin settings as raw text because
                // administrators need to enter HTML. Before adding it to the exported
                // credential JSON, Moodle purifier removes unsafe tags and attributes.
                $cleanhtml = clean_text($customhtml, FORMAT_HTML);

                if (trim($cleanhtml) !== '') {
                    return self::replace_template_variables(
                        $cleanhtml,
                        $record,
                        $credentialtitle,
                        $studentname,
                        $claimtitle,
                        $issuername
                    );
                }
            }
        }

        return self::default_html_template();
    }

    /**
     * Replaces admin-friendly placeholders in the custom HTML template.
     *
     * These placeholders allow administrators to design a visual credential
     * without editing PHP code. Values are escaped before insertion to avoid
     * injecting unsafe learner, course or issuer data into the HTML.
     *
     * @param string $html Clean custom HTML.
     * @param \stdClass $record Local credential record.
     * @param string $credentialtitle Credential display title.
     * @param string $studentname Learner full name.
     * @param string $claimtitle Achievement or course title.
     * @param string $issuername Issuer legal name.
     * @return string HTML with replaced variables.
     */
    private static function replace_template_variables(
        string $html,
        \stdClass $record,
        string $credentialtitle,
        string $studentname,
        string $claimtitle,
        string $issuername
    ): string {
        global $CFG;

        $issuedat = 0;

        if (!empty($record->issuedat)) {
            $issuedat = (int) $record->issuedat;
        } else if (!empty($record->timecreated)) {
            $issuedat = (int) $record->timecreated;
        }

        $issuedate = $issuedat > 0 ? userdate($issuedat, get_string('strftimedate', 'langconfig')) : '';

        $verificationurl = '';
        if (!empty($record->verificationtoken)) {
            $verificationurl = $CFG->wwwroot . '/local/edc_exporter/verify.php?token=' . urlencode($record->verificationtoken);
        }

        // Variables available in the custom HTML field.
        // Empty values are intentional: they prevent unreplaced {{variables}}
        // appearing in the EDC Viewer when optional data is not available.
        $replacements = [
            '{{credential_title}}' => s($credentialtitle),
            '{{learner_name}}' => s($studentname),
            '{{course_name}}' => s($claimtitle),
            '{{issuer_name}}' => s($issuername),
            '{{issue_date}}' => s($issuedate),
            '{{verification_url}}' => s($verificationurl),

            // Optional fields. They are left empty until the builder receives
            // these values from course metadata or plugin settings.
            '{{issuer_logo}}' => '',
            '{{eqf_level}}' => '',
            '{{workload}}' => '',
            '{{learning_outcomes}}' => '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $html);
    }

    /**
     * Resolves the visual page image for EDC individualDisplay.
     *
     * @param string $mode Display mode.
     * @param string $credentialtitle Credential title.
     * @param string $studentname Learner full name.
     * @param string $claimtitle Achievement title.
     * @param string $issuername Issuer legal name.
     * @param array $settings Plugin settings used by the guided template builder.
     * @return array|null MediaObject or null when no image can be produced.
     */
    private static function resolve_cover_image(
        string $mode,
        string $credentialtitle,
        string $studentname,
        string $claimtitle,
        string $issuername,
        array $settings
    ): ?array {
        // The template builder always generates a full cover image.
        // The old "background_image" mode is no longer exposed in settings.
        return cover_image_builder::build_cover_image_media_object(
            $credentialtitle,
            $studentname,
            $claimtitle,
            $issuername,
            $settings
        );
    }

    /**
     * Default HTML fallback template for viewers without individualDisplay support.
     *
     * @return string HTML template.
     */
    public static function default_html_template(): string {
        return <<<'HTML'
    <div>
        <h1 th:text="${credential.displayParameter.title}"></h1>
        <p th:text="${credential.credentialSubject.fullName}"></p>
        <p th:text="${credential.credentialSubject.hasClaim[0].title}"></p>
        <p th:text="${credential.credentialSubject.hasClaim[0].awardedBy.awardingBody[0].legalName}"></p>
    </div>
    HTML;
    }
}
