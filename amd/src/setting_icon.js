// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Updates the icon picker preview when another icon is chosen.
 *
 * @module     local_parce/setting_icon
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Bind the preview to the radio group.
 *
 * @param {string} id Toggle button id
 */
export const init = (id) => {
    const toggle = document.getElementById(id);
    const root = toggle ? toggle.closest('.local-parce-iconpicker') : null;
    if (!root || root.dataset.initialised) {
        return;
    }
    root.dataset.initialised = '1';

    const previewImg = root.querySelector('.local-parce-iconpicker-current');
    const previewLabel = root.querySelector('.local-parce-iconpicker-label');
    root.querySelectorAll('input[type="radio"]').forEach((input) => {
        input.addEventListener('change', () => {
            const option = input.closest('.local-parce-iconpicker-option');
            if (!option) {
                return;
            }
            const img = option.querySelector('img');
            const label = option.querySelector('span');
            if (previewImg && img) {
                previewImg.src = img.src;
            }
            if (previewLabel && label) {
                previewLabel.textContent = label.textContent;
            }
        });
    });
};
