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

use local_edc_exporter\local\setup\field_definition;


/**
 * Builds JSON-LD data for a European Digital Credential.
 *
 * This class does not read the database or store files. It receives data already
 * prepared by export_service and organises it in the structure expected by the EDC exporter.
 *
 * It returns two outputs:
 * - export_json: official file downloaded or sent to external tools.
 * - internal_json: internal file with extra data to review how the credential was generated.
 *
 * @package    local_edc_exporter
 * @copyright  2026 Skills Divers
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jsonld_builder {
    /**
     * Creates the official JSON and internal JSON for a credential.
     *
     * @param \stdClass $user Moodle user receiving the credential.
     * @param \stdClass $course Moodle course completed by the user.
     * @param \stdClass $record local_edcexport_cred record storing status and identifiers.
     * @param array $gradeinfo Course final grade, if any.
     * @param array $metadata Course custom fields used to describe the credential.
     * @param array $settings Global plugin settings, such as issuer data.
     * @return array Returns two keys: internal_json and export_json.
     */
    public static function build(
        \stdClass $user,
        \stdClass $course,
        \stdClass $record,
        array $gradeinfo,
        array $metadata,
        array $settings
    ): array {
        global $CFG;

        $languagecode = 'en';
        $learner = self::build_learner($user);

        $allowlocalhost = !empty($settings['include_urls_when_localhost']);
        $courseurl = edc_concept_helper::build_course_url($CFG->wwwroot, (int) $course->id, $allowlocalhost);
        $issuerhomepage = edc_concept_helper::normalise_public_url((string) $settings['awarding_body_homepage'], $allowlocalhost);
        $awardingbody = self::build_awarding_body($settings, $issuerhomepage);
        $issuer = self::build_issuer($settings, $languagecode, $issuerhomepage);
        $learningoutcomes = self::build_learning_outcomes($metadata, (int) $course->id);
        $specifiedby = self::build_specified_by(
            $metadata,
            $gradeinfo,
            $languagecode,
            (int) $course->id,
            $course,
            $learningoutcomes,
            $courseurl
        );

        $coursefullname = format_string($course->fullname);
        $coursedesc = trim(strip_tags((string) $course->summary));
        if ($coursedesc === '') {
            $coursedesc = $coursefullname;
        }

        $description = [
        'es' => [
            trim(strip_tags(
                edc_concept_helper::first_metadata($metadata, ['credential_description_es']) ?: $coursedesc
            )),
        ],
        'en' => [
            trim(strip_tags(
                edc_concept_helper::first_metadata($metadata, ['credential_description_en']) ?: $coursedesc
            )),
        ],
        ];

        $issued       = gmdate('c', (int) $record->timemodified);
        $awardingdate = gmdate('c', (int) $record->completiontime);
        $issuerlegalname = $settings['awarding_body_legal_name'] ?? '';

        // Generates a 794x1123 JPEG cover image with GD.
        // If GD is unavailable, null is returned and the viewer will use the fallback HTML template.
        $display = display_template_service::build(
            $record,
            $coursefullname,
            $learner['fullname'],
            $coursefullname,
            $issuerlegalname,
            $settings
        );

        // IndividualDisplay is added only when the image was generated successfully.
        $individualdisplay = $display['individualDisplay'];
        $exportjson = [
            'credential' => [
                'id'   => 'urn:credential:' . $record->credentialid,
                'type' => ['VerifiableCredential', 'EuropeanDigitalCredential'],

                'credentialSchema' => [[
                    'id'   => 'http://data.europa.eu/snb/model/ap/edc-generic-full',
                    'type' => 'ShaclValidator2017',
                ]],

                'issuanceDate' => $issued,
                'issued'       => $issued,
                'validFrom'    => $awardingdate,

                'credentialSubject' => [
                    'id'   => 'urn:epass:person:' . (int) $user->id,
                    'type' => 'Person',

                    'fullName'   => edc_concept_helper::lang_map($languagecode, $learner['fullname']),
                    'givenName'  => edc_concept_helper::lang_map($languagecode, $learner['givenname']),
                    'familyName' => edc_concept_helper::lang_map($languagecode, $learner['familyname']),

                    'contactPoint' => [[
                        'id'   => 'urn:epass:contactPoint:user:' . (int) $user->id,
                        'type' => 'ContactPoint',
                        'emailAddress' => [[
                            'id'   => 'mailto:' . $user->email,
                            'type' => 'Mailbox',
                        ]],
                    ]],

                    'hasClaim' => [[
                        'id'    => 'urn:epass:achievement:' . (int) $course->id . ':' . (int) $user->id,
                        'type'  => 'LearningAchievement',
                        'title' => edc_concept_helper::lang_map($languagecode, $coursefullname),
                        'description' => $description,

                        'awardedBy' => [
                            'id'           => 'urn:epass:awardingProcess:' . (int) $record->id,
                            'type'         => 'AwardingProcess',
                            'awardingBody' => [$awardingbody],
                        ],

                        'specifiedBy' => $specifiedby,
                    ]],
                ],

                'issuer' => $issuer,

                'credentialProfiles' => [[
                    'id'   => 'http://data.europa.eu/snb/credential/e34929035b',
                    'type' => 'Concept',
                    'inScheme' => [
                        'id'   => 'http://data.europa.eu/snb/credential/25831c2',
                        'type' => 'ConceptScheme',
                    ],
                    'prefLabel' => ['en' => ['Generic']],
                ]],

                'displayParameter' => [
                    'id'   => 'urn:epass:displayParameter:' . (int) $record->id,
                    'type' => 'DisplayParameter',

                    'primaryLanguage' => edc_concept_helper::language_concept('en'),
                    'language' => [
                        edc_concept_helper::language_concept('en'),
                        edc_concept_helper::language_concept('es'),
                    ],

                    'title' => edc_concept_helper::lang_map($languagecode, $coursefullname),

                    'description' => [
                        'es' => ['Micro-credential issued by the institution configured in Moodle.'],
                        'en' => ['Micro-credential issued by the institution configured in Moodle.'],
                    ],

                    // 794x1123 JPEG cover generated with GD.
                    // EDC-compatible viewers can prioritise it over the HTML template.
                    'individualDisplay' => $individualdisplay,
                ],
            ],

            'deliveryDetails' => [
                'deliveryAddress' => [$user->email],

                // Fallback HTML template for viewers that do not support individualDisplay.
                'displayDetails' => [
                    'template' => $display['template'],
                ],
            ],
        ];

        $internaljson = [
            'moodle_tracking' => [
                'plugin'          => 'local_edc_exporter',
                'recordid'        => (int) $record->id,
                'credentialid'    => $record->credentialid,
                'userid'          => (int) $user->id,
                'username'        => (string) $user->username,
                'courseid'        => (int) $course->id,
                'courseshortname' => (string) $course->shortname,
                'completionid'    => (int) $record->completionid,
                'completiontime'  => (int) $record->completiontime,
                'status'          => (string) $record->status,
            ],
            'source_data' => [
                'user' => [
                    'id'        => (int) $user->id,
                    'firstname' => (string) $learner['givenname'],
                    'lastname'  => (string) $learner['familyname'],
                    'fullname'  => (string) $learner['fullname'],
                    'email'     => (string) $user->email,
                ],
                'course' => [
                    'id'        => (int) $course->id,
                    'shortname' => (string) $course->shortname,
                    'fullname'  => $coursefullname,
                    'summary'   => $coursedesc,
                ],
                'gradeinfo' => $gradeinfo,
                'metadata'  => $metadata,
                'settings'  => $settings,
            ],
            'supported_edc_fields' => field_definition::get_fields(),
            'field_mapping_report' => field_definition::build_report($metadata),
            'export_json'          => $exportjson,
        ];

        return [
            'internal_json' => $internaljson,
            'export_json'   => $exportjson,
        ];
    }

    // Private builders.
    /**
     * Builds the learner block for the EDC JSON-LD credential.
     *
     * @param \stdClass $user Moodle user record.
     * @return array Learner data formatted for the credential.
     */
    private static function build_learner(\stdClass $user): array {
        // Some external EDC tools fail when givenName or familyName are present but empty.
        // Moodle usually requires firstname and lastname, but imported users, test users,
        // external authentication plugins or anonymised accounts may leave one field empty.
        $firstname = edc_concept_helper::clean_text((string) ($user->firstname ?? ''));
        $lastname  = edc_concept_helper::clean_text((string) ($user->lastname ?? ''));

        // Start with the real Moodle name fields. If they are incomplete, use Moodle's
        // visible full name and then safe technical fallbacks to avoid empty recipient data.
        $fullname = trim($firstname . ' ' . $lastname);
        $moodlefullname = edc_concept_helper::clean_text(fullname($user));
        if ($fullname === '' && $moodlefullname !== '') {
            $fullname = $moodlefullname;
        }

        if ($fullname === '') {
            $email = edc_concept_helper::clean_text((string) ($user->email ?? ''));
            $atpos = strpos($email, '@');
            if ($atpos !== false && $atpos > 0) {
                $fullname = substr($email, 0, $atpos);
            }
        }

        if ($fullname === '') {
            $fullname = edc_concept_helper::clean_text((string) ($user->username ?? ''));
        }

        // Make technical identifiers more readable when they are used as fallback names.
        $fullname = trim((string) preg_replace('/[._-]+/', ' ', $fullname));
        $fullname = trim((string) preg_replace('/\s+/', ' ', $fullname));

        // If one Moodle name part is missing, derive it from the best available full name.
        // This keeps fullName, givenName and familyName populated for stricter viewers.
        $parts = preg_split('/\s+/', $fullname) ?: [];
        $parts = array_values(array_filter($parts, static function($part): bool {
            return trim((string) $part) !== '';
        }));

        if ($firstname === '' && !empty($parts)) {
            $firstname = $parts[0];
        }

        if ($lastname === '' && count($parts) > 1) {
            $lastname = implode(' ', array_slice($parts, 1));
        }

        // Final fallback: never leave EDC recipient name fields empty.
        // If the source system only has one display name, it is repeated as the family name
        // to avoid external diploma template errors caused by missing recipient data.
        if ($firstname === '') {
            $firstname = $fullname;
        }
        if ($lastname === '') {
            $lastname = $fullname;
        }
        if ($fullname === '') {
            $fullname = trim($firstname . ' ' . $lastname);
        }

        return [
            'givenname'  => $firstname,
            'familyname' => $lastname,
            'fullname'   => $fullname,
        ];
    }

    /**
     * Builds the awarding body data from plugin settings.
     *
     * @param array $settings Plugin configuration values.
     * @param string|null $homepage Optional awarding body homepage URL.
     * @return array Awarding body data formatted for the credential.
     */
    private static function build_awarding_body(array $settings, ?string $homepage): array {
        global $CFG;

        $supportemail = '';
        if (!empty($CFG->supportemail)) {
            $supportemail = $CFG->supportemail;
        } else if (!empty($CFG->noreplyaddress)) {
            $supportemail = $CFG->noreplyaddress;
        }

        $identifier = $settings['awarding_body_identifier'] ?: 'urn:epass:org:awardingbody:default';
        $notation   = $settings['awarding_body_identifier']
            ?: clean_param($settings['awarding_body_legal_name'], PARAM_ALPHANUMEXT);

        $body = [
            'id'        => $identifier,
            'type'      => 'Organisation',
            'legalName' => [
                'en' => [$settings['awarding_body_legal_name']],
                'es' => [$settings['awarding_body_legal_name']],
            ],
            'identifier' => [[
                'id'       => 'urn:epass:identifier:awardingbody',
                'type'     => 'Identifier',
                'notation' => $notation,
            ]],
            'contactPoint' => [[
                'id'   => 'urn:epass:contact:awardingbody',
                'type' => 'ContactPoint',
                'emailAddress' => [[
                    'id'   => 'mailto:' . $supportemail,
                    'type' => 'Mailbox',
                ]],
            ]],
            'altLabel' => [
                'en' => [$settings['awarding_body_legal_name']],
                'es' => [$settings['awarding_body_legal_name']],
            ],
            'location' => [[
                'id'   => 'urn:epass:location:awardingbody',
                'type' => 'Location',
                'address' => [[
                    'id'          => 'urn:epass:address:awardingbody',
                    'type'        => 'Address',
                    'countryCode' => edc_concept_helper::country_concept(
                        $settings['awarding_body_country'],
                        $settings['awarding_body_country_label']
                    ),
                ]],
            ]],
        ];

        if ($homepage !== null) {
            $body['homepage'] = [[
                'id'         => 'urn:epass:webResource:awardingbody-homepage',
                'type'       => 'WebResource',
                'contentURL' => $homepage,
            ]];
        }

        $logomediaobject = cover_image_builder::build_logo_media_object('urn:epass:mediaObject:awardingbody-logo');
        if ($logomediaobject !== null) {
            $body['logo'] = $logomediaobject;
        }

        return $body;
    }

    /**
     * Builds the issuer organisation block.
     *
     * @param array $settings Plugin configuration values.
     * @param string $languagecode Credential language code.
     * @param string|null $homepage Optional issuer homepage URL.
     * @return array Issuer data formatted for the credential.
     */
    private static function build_issuer(array $settings, string $languagecode, ?string $homepage): array {
        $issuer = [
            'id'        => 'urn:epass:issuer:moodle-site',
            'type'      => 'Organisation',
            'legalName' => edc_concept_helper::lang_map($languagecode, $settings['awarding_body_legal_name']),
            'location'  => [
                'address' => [
                    'countryCode' => edc_concept_helper::country_concept(
                        $settings['awarding_body_country'],
                        $settings['awarding_body_country_label']
                    ),
                ],
            ],
        ];

        if ($homepage !== null) {
            $issuer['homepage'] = [[
                'id'         => 'urn:epass:webResource:issuer-homepage',
                'type'       => 'WebResource',
                'contentURL' => $homepage,
            ]];
        }

        $logomediaobject = cover_image_builder::build_logo_media_object('urn:epass:mediaObject:issuer-logo');
        if ($logomediaobject !== null) {
            $issuer['logo'] = $logomediaobject;
        }

        return $issuer;
    }

    /**
     * Builds the learning achievement specification block.
     *
     * @param \stdClass $course Moodle course record.
     * @param array $metadata Course metadata values.
     * @param string $languagecode Credential language code.
     * @return array Achievement specification data.
     */
    private static function build_specified_by(
        array $metadata,
        array $gradeinfo,
        string $languagecode,
        int $courseid,
        \stdClass $course,
        array $learningoutcomes,
        ?string $courseurl
    ): array {
        $coursefullname = format_string($course->fullname);
        $coursedesc     = trim(strip_tags((string) $course->summary));
        if ($coursedesc === '') {
            $coursedesc = $coursefullname;
        }

        $workload = edc_concept_helper::workload_to_iso8601(
            edc_concept_helper::first_metadata($metadata, ['credential_workload_hours', 'edc_hours'])
        );
        $modality = edc_concept_helper::first_metadata($metadata, ['credential_modality', 'edc_modality']);
        $eqflevel = edc_concept_helper::first_metadata($metadata, ['credential_eqf_level', 'edc_eqf_level']);
        $qa       = edc_concept_helper::first_metadata($metadata, ['credential_quality_assurance', 'edc_quality_assurance'])
            ?: 'Internal course validation';

        $spec = [
            'id'          => 'urn:epass:achievementSpec:' . $courseid,
            'type'        => 'LearningAchievementSpecification',
            'title'       => edc_concept_helper::lang_map($languagecode, $coursefullname),
            'description' => [
                'es' => [
                    trim(strip_tags(
                        edc_concept_helper::first_metadata($metadata, ['credential_description_es']) ?: $coursedesc
                    )),
                ],
                'en' => [
                    trim(strip_tags(
                        edc_concept_helper::first_metadata($metadata, ['credential_description_en']) ?: $coursedesc
                    )),
                ],
            ],
        ];

        if (!empty($learningoutcomes)) {
            $spec['learningOutcome'] = $learningoutcomes;
        }

        if ($workload !== '') {
            $spec['volumeOfLearning'] = $workload;
        }

        $ects = edc_concept_helper::first_metadata($metadata, ['credential_ects', 'edc_ects']);
        if ($ects !== '') {
            $spec['creditPoint'] = [[
                'id'        => 'urn:epass:creditPoint:' . $courseid,
                'type'      => 'CreditPoint',
                'framework' => [
                    'id'       => 'http://data.europa.eu/snb/education-credit/6fcec5c5af',
                    'type'     => 'Concept',
                    'inScheme' => [
                        'id'   => 'http://data.europa.eu/snb/education-credit/25831c2',
                        'type' => 'ConceptScheme',
                    ],
                    'prefLabel' => ['en' => ['European Credit Transfer System']],
                ],
                'point' => $ects,
            ]];
        }

        if ($courseurl !== null) {
            $spec['homepage'] = [[
                'id'         => 'urn:epass:webResource:spec:' . $courseid,
                'type'       => 'WebResource',
                'contentURL' => $courseurl,
            ]];
        }

        $notes = [];
        if ($modality !== '') {
            $notes[] = [
                'id'          => 'urn:epass:note:modality:' . $courseid,
                'type'        => 'Note',
                'noteLiteral' => [
                    'en' => ['Form of participation: ' . edc_concept_helper::modality_label($modality)],
                    'es' => ['Form of participation: ' . edc_concept_helper::modality_label_es($modality)],
                ],
            ];
        }
        $notes[] = [
            'id'          => 'urn:epass:note:qa:' . $courseid,
            'type'        => 'Note',
            'noteLiteral' => [
                'en' => ['Quality assurance: ' . $qa],
                'es' => ['Quality assurance: ' . $qa],
            ],
        ];
        if ($eqflevel !== '') {
            $notes[] = [
                'id'          => 'urn:epass:note:eqf:' . $courseid,
                'type'        => 'Note',
                'noteLiteral' => [
                    'en' => ['EQF level: ' . $eqflevel],
                    'es' => ['EQF level: ' . $eqflevel],
                ],
            ];
        }

        if (!empty($notes)) {
            $spec['additionalNote'] = $notes;
        }

        $spec['provenBy'] = [self::build_legacy_assessment_spec($metadata, $gradeinfo, $languagecode, $courseid)];

        return $spec;
    }

    /**
     * Builds the learning outcomes list from course metadata.
     *
     * @param array $metadata Course metadata values.
     * @param int $courseid Moodle course ID.
     * @return array Learning outcomes formatted for the credential.
     */
    private static function build_learning_outcomes(array $metadata, int $courseid): array {
        $rawes = edc_concept_helper::first_metadata($metadata, ['credential_learning_outcomes_es']);
        $rawen = edc_concept_helper::first_metadata($metadata, ['credential_learning_outcomes', 'edc_learning_outcomes']);

        $rawes = str_replace(['<br>', '<br/>', '<br />'], "\n", $rawes);
        $rawen = str_replace(['<br>', '<br/>', '<br />'], "\n", $rawen);

        $lineses = preg_split('/\R+/', $rawes) ?: [];
        $linesen = preg_split('/\R+/', $rawen) ?: [];

        $outcomes = [];
        $counter  = 1;
        $max      = max(count($lineses), count($linesen));

        for ($i = 0; $i < $max; $i++) {
            $es = isset($lineses[$i]) ? trim(strip_tags($lineses[$i])) : '';
            $en = isset($linesen[$i]) ? trim(strip_tags($linesen[$i])) : '';
            if ($es === '' && $en === '') {
                continue;
            }
            $outcomes[] = [
                'id'    => 'urn:epass:learningOutcome:' . $courseid . ':' . $counter,
                'type'  => 'LearningOutcome',
                'title' => [
                    'en' => [$en !== '' ? $en : $es],
                    'es' => [$es !== '' ? $es : $en],
                ],
            ];
            $counter++;
        }

        return $outcomes;
    }

    /**
     * Builds the legacy assessment specification block.
     *
     * @param array $metadata Course metadata values.
     * @param string $languagecode Credential language code.
     * @return array|null Assessment specification data or null when unavailable.
     */
    private static function build_legacy_assessment_spec(
        array $metadata,
        array $gradeinfo,
        string $languagecode,
        int $courseid
    ): array {
        $assessmenttype = edc_concept_helper::first_metadata($metadata, ['credential_assessment_type', 'edc_assessment_type'])
            ?: 'Final graded assessment';

        $spec = [
            'id'    => 'urn:epass:assessmentSpec:' . $courseid,
            'type'  => 'LearningAssessmentSpecification',
            'title' => edc_concept_helper::lang_map($languagecode, $assessmenttype),
        ];

        if (!empty($gradeinfo) && isset($gradeinfo['grade']) && $gradeinfo['grade'] !== null) {
            $spec['grade'] = [
                'id'          => 'urn:epass:grade:' . $courseid,
                'type'        => 'Note',
                'noteLiteral' => edc_concept_helper::lang_map(
                    $languagecode,
                    (string) ($gradeinfo['str'] ?? $gradeinfo['grade'])
                ),
                'notation' => (string) $gradeinfo['grade'],
            ];
        }

        return $spec;
    }
}
