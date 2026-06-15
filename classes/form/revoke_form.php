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

namespace local_edc_exporter\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Form used to revoke an issued credential.
 *
 * @package    local_edc_exporter
 * @copyright  2026 Skills Divers
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class revoke_form extends \moodleform {
    /** Maximum length accepted for a revocation reason. */
    private const MAX_REASON_LENGTH = 1000;

    /**
     * Defines the revocation form fields.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;

        // Hidden credential id keeps the POST tied to the selected credential.
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // Keep the reason plain text to avoid storing or rendering unsafe HTML.
        $mform->addElement(
            'textarea',
            'revocationreason',
            get_string('revocationreason', 'local_edc_exporter'),
            ['rows' => 6, 'cols' => 60]
        );
        $mform->setType('revocationreason', PARAM_TEXT);
        $mform->addRule(
            'revocationreason',
            get_string('required'),
            'required',
            null,
            'client'
        );
        $mform->addRule(
            'revocationreason',
            get_string('revocationreasonmaxlength', 'local_edc_exporter'),
            'maxlength',
            self::MAX_REASON_LENGTH,
            'client'
        );

        $this->add_action_buttons(true, get_string('confirmrevoke', 'local_edc_exporter'));
    }

    /**
     * Validates submitted revocation data.
     *
     * @param array $data Submitted form data.
     * @param array $files Submitted files.
     * @return array Validation errors.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        if (
            isset($data['revocationreason'])
            && \core_text::strlen((string) $data['revocationreason']) > self::MAX_REASON_LENGTH
        ) {
            $errors['revocationreason'] = get_string('revocationreasonmaxlength', 'local_edc_exporter');
        }

        return $errors;
    }
}
