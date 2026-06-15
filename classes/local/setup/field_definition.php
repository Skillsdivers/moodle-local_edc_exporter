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

/**
 * Defines the data fields the plugin can use to build the EDC JSON.
 *
 * This class does not generate JSON or read the database. Its purpose is to
 * centralise which data exists, what it is used for, and whether it is required or optional.
 *
 * Later, these definitions can support mapping each EDC field to a different
 * custom field in each Moodle installation.
 *
 * @package    local_edc_exporter
 * @copyright  2026 Skills Divers
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field_definition {
    /**
     * Returns the list of fields supported by the plugin.
     *
     * @return array Map of internal key => field definition.
     */
    public static function get_fields(): array {
        return [
            'description_es' => [
                'label' => 'Credential description ES',
                'defaultsource' => 'credential_description_es',
                'required' => false,
                'jsonarea' => 'credential.credentialSubject.hasClaim.description.es',
                'example' => 'Short credential description in Spanish.',
            ],
            'description_en' => [
                'label' => 'Credential description EN',
                'defaultsource' => 'credential_description_en',
                'required' => false,
                'jsonarea' => 'credential.credentialSubject.hasClaim.description.en',
                'example' => 'Short credential description in English.',
            ],
            'learning_outcomes' => [
                'label' => 'Learning outcomes',
                'defaultsource' => 'credential_learning_outcomes',
                'fallbacksources' => ['edc_learning_outcomes', 'credential_learning_outcomes_es'],
                'required' => true,
                'jsonarea' => 'credential.credentialSubject.hasClaim.specifiedBy.learningOutcome',
                'example' => 'One line per learning outcome.',
            ],
            'workload_hours' => [
                'label' => 'Workload hours',
                'defaultsource' => 'credential_workload_hours',
                'fallbacksources' => ['edc_hours'],
                'required' => true,
                'jsonarea' => 'credential.credentialSubject.hasClaim.specifiedBy.volumeOfLearning',
                'example' => '120',
            ],
            'modality' => [
                'label' => 'Learning modality',
                'defaultsource' => 'credential_modality',
                'fallbacksources' => ['edc_modality'],
                'required' => false,
                'jsonarea' => 'credential.credentialSubject.hasClaim.specifiedBy.learningSetting',
                'example' => 'online, blended, face-to-face',
            ],
            'eqf_level' => [
                'label' => 'EQF level',
                'defaultsource' => 'credential_eqf_level',
                'fallbacksources' => ['edc_eqf_level'],
                'required' => false,
                'jsonarea' => 'credential.credentialSubject.hasClaim.specifiedBy.eqfLevel',
                'example' => '4',
            ],
            'assessment' => [
                'label' => 'Assessment method',
                'defaultsource' => 'credential_assessment_type',
                'fallbacksources' => ['edc_assessment_type'],
                'required' => false,
                'jsonarea' => 'credential.credentialSubject.hasClaim.specifiedBy.assessment',
                'example' => 'Final quiz, practical task, portfolio.',
            ],
        ];
    }

    /**
     * Creates a simple report showing which defined fields exist in course metadata.
     *
     * This report does not block generation. It only helps review whether a course
     * has the data needed to build a complete JSON file.
     *
     * @param array $metadata Actual course custom fields.
     * @return array Presence report for each EDC field.
     */
    public static function build_report(array $metadata): array {
        $report = [];

        foreach (self::get_fields() as $key => $definition) {
            $source = $definition['defaultsource'];
            $sourceschecked = [$source];

            // The system checks the main field first, then any compatible alias
            // defined as a fallbacksource.
            if (!empty($definition['fallbacksources']) && is_array($definition['fallbacksources'])) {
                $sourceschecked = array_merge($sourceschecked, $definition['fallbacksources']);
            }

            $value = '';
            $resolvedsource = null;

            foreach ($sourceschecked as $candidate) {
                $candidatevalue = trim((string) ($metadata[$candidate] ?? ''));

                if ($candidatevalue !== '') {
                    $value = $candidatevalue;
                    $resolvedsource = $candidate;
                    break;
                }
            }

            $cleanvalue = self::clean_metadata_value($value);

            $report[$key] = [
                'label' => $definition['label'],
                'source' => $resolvedsource ?: $source,
                'checked_sources' => $sourceschecked,
                'required' => (bool) $definition['required'],
                'found' => $value !== '',
                'value_preview' => $value !== '' ? mb_substr($cleanvalue, 0, 90) : null,
                'full_value' => $value !== '' ? $cleanvalue : null,
                'jsonarea' => $definition['jsonarea'],
            ];
        }

        return $report;
    }

    /**
     * Cleans an HTML metadata value for readable reports.
     *
     * @param string $value Original custom field value.
     * @return string Value converted to plain text.
     */
    private static function clean_metadata_value(string $value): string {
        $value = strip_tags($value, '<br><p>');
        $value = str_replace(
            ['<br>', '<br/>', '<br />', '</p>', '<p>'],
            ["\n", "\n", "\n", "\n", ''],
            $value
        );

        return trim(preg_replace('/\n+/', "\n", $value));
    }
}
