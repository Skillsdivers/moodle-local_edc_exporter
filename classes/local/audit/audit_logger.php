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

namespace local_edc_exporter\local\audit;


/**
 * Minimal audit logger for credential actions.
 *
 * This class stores only technical traceability data. It must not store
 * full credential JSON, learner email, names, or other unnecessary personal data.
 *
 * @package    local_edc_exporter
 * @copyright  2026 Skills Divers
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class audit_logger {
    /** Credential was issued for the first time. */
    public const ACTION_ISSUED = 'issued';

    /** Credential was regenerated. */
    public const ACTION_REGENERATED = 'regenerated';

    /** Credential was downloaded. */
    public const ACTION_DOWNLOADED = 'downloaded';

    /** Credential page was viewed. */
    public const ACTION_VIEWED = 'viewed';

    /** Credential was revoked. */
    public const ACTION_REVOKED = 'revoked';

    /** Credential was verified using the public token. */
    public const ACTION_VERIFIED = 'verified';

    /** Error related to credential generation or handling. */
    public const ACTION_ERROR = 'error';

    /**
     * Writes one audit log record.
     *
     * @param string $action Action name.
     * @param int|null $credentialid Internal credential record id.
     * @param int|null $userid Credential owner user id.
     * @param int|null $courseid Related course id.
     * @param int|null $actorid User who performed the action. Null for public verification.
     * @param array $details Minimal technical details, without sensitive data.
     * @return void
     */
    public static function log(
        string $action,
        ?int $credentialid = null,
        ?int $userid = null,
        ?int $courseid = null,
        ?int $actorid = null,
        array $details = []
    ): void {
        global $DB;

        // Only allow expected audit actions to avoid uncontrolled values.
        $allowedactions = [
            self::ACTION_ISSUED,
            self::ACTION_REGENERATED,
            self::ACTION_DOWNLOADED,
            self::ACTION_VIEWED,
            self::ACTION_REVOKED,
            self::ACTION_VERIFIED,
            self::ACTION_ERROR,
        ];

        if (!in_array($action, $allowedactions, true)) {
            $action = self::ACTION_ERROR;
            $details = ['reason' => 'Invalid audit action'];
        }

        // Keep details small and technical. Never save complete credential JSON here.
        $detailsjson = null;
        if (!empty($details)) {
            $detailsjson = json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $record = (object) [
            'credentialid' => $credentialid,
            'userid' => $userid,
            'courseid' => $courseid,
            'actorid' => $actorid,
            'action' => $action,
            'details' => $detailsjson,
            'timecreated' => time(),
        ];

        $DB->insert_record('local_edc_exporter_log', $record);
    }
}
