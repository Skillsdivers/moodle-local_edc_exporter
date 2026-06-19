// This file is part of Moodle - https://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.

const selectors = {
    mode: '[name="s_local_edc_exporter_display_mode"]',
    customHtml: '[name="s_local_edc_exporter_display_custom_html"]',
    header: '[name="s_local_edc_exporter_display_template_header"]',
    footer: '[name="s_local_edc_exporter_display_template_footer"]',
    background: '[name="s_local_edc_exporter_display_template_background"]',
    customBackground: '[name="s_local_edc_exporter_display_template_background_customcolor"]',
};

const getSettingRow = field => field?.closest('.form-item, .form-group, .mb-3, .admin_setting');

const setVisible = (field, visible) => {
    const row = getSettingRow(field);
    if (row) {
        row.hidden = !visible;
    }
};

/**
 * Initialise dynamic visibility for credential display settings.
 */
export const init = () => {
    const modeField = document.querySelector(selectors.mode);
    const backgroundField = document.querySelector(selectors.background);

    if (!modeField) {
        return;
    }

    const refresh = () => {
        const templateBuilder = modeField.value === 'template_builder';

        [selectors.header, selectors.footer, selectors.background].forEach(selector => {
            setVisible(document.querySelector(selector), templateBuilder);
        });

        setVisible(
            document.querySelector(selectors.customBackground),
            templateBuilder && backgroundField?.value === 'customcolor'
        );
        setVisible(
            document.querySelector(selectors.customHtml),
            modeField.value === 'custom_html'
        );
    };

    modeField.addEventListener('change', refresh);
    backgroundField?.addEventListener('change', refresh);
    refresh();
};
