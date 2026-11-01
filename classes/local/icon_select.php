<?php
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

namespace local_parce\local;

/**
 * Admin control that stores one icon file name and previews the SVG.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class icon_select extends \admin_setting_configselect {
    /**
     * Build the control from the SVG files in pix/icons.
     *
     * @param string $name Setting name, including the plugin prefix
     * @param string $visiblename Localised name
     * @param string $description Localised description
     * @param string $defaultsetting Default file name
     */
    public function __construct(
        string $name,
        string $visiblename,
        string $description,
        string $defaultsetting = appearance::DEFAULT_ICON
    ) {
        parent::__construct($name, $visiblename, $description, $defaultsetting, appearance::get_icon_choices());
    }

    /**
     * Render the current icon and a popover of the other icons.
     *
     * @param mixed $data Selected icon name, or null when unset
     * @param string $query Admin search query
     * @return string
     */
    public function output_html($data, $query = '') {
        global $OUTPUT, $PAGE;

        $default = $this->get_defaultsetting();
        $current = $this->get_setting();

        if (!$this->load_choices() || empty($this->choices)) {
            return '';
        }

        $warning = '';
        if ($current === null) {
            if (is_string($default) && array_key_exists($default, $this->choices)) {
                $data = $default;
            }
        } else if (!array_key_exists((string) $current, $this->choices)) {
            $warning = get_string('warningcurrentsetting', 'admin', $current);
            if (is_string($default) && (string) $data === (string) $current && array_key_exists($default, $this->choices)) {
                $data = $default;
            }
        }

        if (!is_string($data) || !array_key_exists($data, $this->choices)) {
            $data = is_string($default) && array_key_exists($default, $this->choices)
                ? $default
                : (string) array_key_first($this->choices);
        }

        $icons = [];
        foreach ($this->choices as $value => $label) {
            $icons[] = [
                'value' => $value,
                'label' => $label,
                'name' => $this->get_full_name(),
                'url' => $OUTPUT->image_url(appearance::pix_name($value), 'local_parce')->out(false),
                'selected' => (string) $value === (string) $data,
                'readonly' => $this->is_readonly(),
            ];
        }

        $element = $OUTPUT->render_from_template('local_parce/setting_icon', [
            'id' => $this->get_id(),
            'name' => $this->get_full_name(),
            'currenturl' => $OUTPUT->image_url(appearance::pix_name($data), 'local_parce')->out(false),
            'currentlabel' => $this->choices[$data],
            'grouplabel' => $this->visiblename,
            'readonly' => $this->is_readonly(),
            'icons' => $icons,
        ]);

        $PAGE->requires->js_call_amd('local_parce/setting_icon', 'init', [$this->get_id()]);

        $defaultinfo = null;
        if (is_string($default) && array_key_exists($default, $this->choices)) {
            $defaultinfo = $this->choices[$default];
        }

        return format_admin_setting(
            $this,
            $this->visiblename,
            $element,
            $this->description,
            true,
            $warning,
            $defaultinfo,
            $query
        );
    }
}
