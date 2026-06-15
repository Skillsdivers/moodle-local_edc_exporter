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

namespace local_edc_exporter\local;

/**
 * Stored file admin setting for issuer and visual design images.
 *
 * @package    local_edc_exporter
 * @copyright  2026 Skills Divers
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_image extends \admin_setting_configstoredfile {
    /** @var int Required image width in pixels. */
    protected int $requiredwidth;

    /** @var int Required image height in pixels. */
    protected int $requiredheight;

    /**
     * Constructor.
     *
     * @param string $name Full setting name.
     * @param string $visiblename Visible setting name.
     * @param string $description Setting description.
     * @param string $filearea File area name.
     * @param int $itemid File item id.
     * @param array $options File picker options.
     * @param int $requiredwidth Required width in pixels.
     * @param int $requiredheight Required height in pixels.
     */
    public function __construct(
        string $name,
        string $visiblename,
        string $description,
        string $filearea,
        int $itemid,
        array $options,
        int $requiredwidth,
        int $requiredheight
    ) {
        parent::__construct($name, $visiblename, $description, $filearea, $itemid, $options);

        $this->requiredwidth = $requiredwidth;
        $this->requiredheight = $requiredheight;
    }

    /**
     * Saves the file and checks its dimensions.
     *
     * @param mixed $data Submitted setting data.
     * @return string Empty string when valid, error text when invalid.
     */
    public function write_setting($data): string {
        $result = parent::write_setting($data);

        if ($result !== '') {
            return $result;
        }

        $fs = get_file_storage();
        $context = \context_system::instance();

        $files = $fs->get_area_files(
            $context->id,
            'local_edc_exporter',
            $this->filearea,
            $this->itemid,
            'id',
            false
        );

        if (empty($files)) {
            return '';
        }

        $file = reset($files);
        $info = @getimagesizefromstring($file->get_content());

        if ($info === false) {
            $file->delete();
            return get_string('displayimageinvalid', 'local_edc_exporter');
        }

        if ((int) $info[0] !== $this->requiredwidth || (int) $info[1] !== $this->requiredheight) {
            $file->delete();

            return get_string(
                'displayimageinvalidsize',
                'local_edc_exporter',
                [
                    'width' => $this->requiredwidth,
                    'height' => $this->requiredheight,
                    'currentwidth' => (int) $info[0],
                    'currentheight' => (int) $info[1],
                ]
            );
        }

        return '';
    }
}
