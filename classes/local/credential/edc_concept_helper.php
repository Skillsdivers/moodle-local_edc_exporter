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
 * EDC JSON-LD helper methods.
 *
 * @package    local_edc_exporter
 * @copyright  2026 Skills Divers
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edc_exporter\local\credential;


/**
 * Builds reusable EDC concepts, language maps and cleaned values.
 */
class edc_concept_helper {
    /**
     * Builds a public course URL when allowed.
     *
     * @param string $wwwroot Moodle site URL.
     * @param int $courseid Course ID.
     * @param bool $allowlocalhost Whether localhost URLs are allowed.
     * @return string|null Course URL or null.
     */
    public static function build_course_url(string $wwwroot, int $courseid, bool $allowlocalhost): ?string {
        $wwwroot = rtrim($wwwroot, '/');

        if (preg_match('~^https?://(localhost|127\.0\.0\.1)(:\d+)?$~i', $wwwroot) && !$allowlocalhost) {
            return null;
        }

        return $wwwroot . '/course/view.php?id=' . $courseid;
    }

    /**
     * Normalises a public URL and rejects localhost unless explicitly allowed.
     *
     * @param string $url URL to normalise.
     * @param bool $allowlocalhost Whether localhost URLs are allowed.
     * @return string|null Public URL or null.
     */
    public static function normalise_public_url(string $url, bool $allowlocalhost): ?string {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if (preg_match('~^https?://(localhost|127\.0\.0\.1)(:\d+)?(/.*)?$~i', $url) && !$allowlocalhost) {
            return null;
        }

        return $url;
    }

    /**
     * Returns the English label for a modality code.
     *
     * @param string $value Modality value.
     * @return string Label.
     */
    public static function modality_label(string $value): string {
        return match (trim($value)) {
            '1' => 'Online learning',
            '2' => 'Blended learning',
            '3' => 'Face-to-face learning',
            default => $value,
        };
    }

    /**
     * Returns a secondary label for a modality code.
     *
     * @param string $value Modality value.
     * @return string Label.
     */
    public static function modality_label_es(string $value): string {
        return match (trim($value)) {
            '1' => 'Online learning',
            '2' => 'Blended learning',
            '3' => 'Face-to-face learning',
            default => $value,
        };
    }

    /**
     * Converts workload hours into ISO 8601 duration format.
     *
     * @param string $hours Workload hours.
     * @return string ISO 8601 duration or original value.
     */
    public static function workload_to_iso8601(string $hours): string {
        $hours = trim($hours);

        if ($hours === '') {
            return '';
        }

        if (is_numeric($hours)) {
            return 'PT' . rtrim(rtrim(number_format((float) $hours, 2, '.', ''), '0'), '.') . 'H';
        }

        return $hours;
    }

    /**
     * Returns the first non-empty metadata value from a list of keys.
     *
     * @param array $metadata Course metadata.
     * @param array $keys Candidate keys.
     * @return string First value found.
     */
    public static function first_metadata(array $metadata, array $keys): string {
        foreach ($keys as $key) {
            if (array_key_exists($key, $metadata) && trim((string) $metadata[$key]) !== '') {
                return trim((string) $metadata[$key]);
            }
        }

        return '';
    }

    /**
     * Cleans text before inserting it into JSON-LD.
     *
     * @param string $value Raw text.
     * @return string Clean UTF-8 text.
     */
    public static function clean_text(string $value): string {
        return trim(mb_convert_encoding($value, 'UTF-8', 'UTF-8'));
    }

    /**
     * Builds a language map used by EDC JSON-LD.
     *
     * @param string $languagecode Language code.
     * @param string $value Text value.
     * @return array Language map.
     */
    public static function lang_map(string $languagecode, string $value): array {
        return [$languagecode => [$value]];
    }

    /**
     * Builds a language concept.
     *
     * @param string $languagecode Language code.
     * @return array EDC language concept.
     */
    public static function language_concept(string $languagecode): array {
        $map = [
            'en' => ['ENG', 'English'],
            'es' => ['SPA', 'Spanish'],
            'fr' => ['FRA', 'French'],
            'de' => ['DEU', 'German'],
            'it' => ['ITA', 'Italian'],
            'pt' => ['POR', 'Portuguese'],
        ];

        [$idcode, $label] = $map[$languagecode] ?? ['ENG', 'English'];

        return [
            'id'       => 'http://publications.europa.eu/resource/authority/language/' . $idcode,
            'type'     => 'Concept',
            'inScheme' => [
                'id'   => 'http://publications.europa.eu/resource/authority/language',
                'type' => 'ConceptScheme',
            ],
            'prefLabel' => ['en' => [$label]],
            'notation'  => 'language',
        ];
    }

    /**
     * Builds a country concept.
     *
     * @param string $code Country code.
     * @param string $label Country label.
     * @return array EDC country concept.
     */
    public static function country_concept(string $code, string $label): array {
        return [
            'id'       => 'http://publications.europa.eu/resource/authority/country/' . strtoupper($code),
            'type'     => 'Concept',
            'inScheme' => [
                'id'   => 'http://publications.europa.eu/resource/authority/country',
                'type' => 'ConceptScheme',
            ],
            'prefLabel' => ['en' => [$label]],
            'notation'  => 'country',
        ];
    }

    /**
     * Builds an EQF concept.
     *
     * @param string $level EQF level.
     * @return array EDC EQF concept.
     */
    public static function eqf_concept(string $level): array {
        $levelint = (int) $level;
        $map = [
            1 => ['c_d2a2b44c', 'EQF Level 1'],
            2 => ['c_1ac3b7e7', 'EQF Level 2'],
            3 => ['c_1aca9e39', 'EQF Level 3'],
            4 => ['c_1d778ac7', 'EQF Level 4'],
            5 => ['c_1e5e4c1a', 'EQF Level 5'],
            6 => ['c_05e1b49f', 'EQF Level 6'],
            7 => ['c_0382df47', 'EQF Level 7'],
            8 => ['c_2571e34a', 'EQF Level 8'],
        ];

        if (isset($map[$levelint])) {
            [$code, $label] = $map[$levelint];

            return [
                'id'       => 'http://data.europa.eu/snb/eqf/' . $code,
                'type'     => 'Concept',
                'inScheme' => [
                    'id'   => 'http://data.europa.eu/snb/eqf/25831c2',
                    'type' => 'ConceptScheme',
                ],
                'prefLabel' => ['en' => [$label]],
            ];
        }

        return [
            'id'        => 'urn:epass:concept:eqf:' . urlencode($level),
            'type'      => 'Concept',
            'prefLabel' => ['en' => ['EQF Level ' . $level]],
        ];
    }
}
