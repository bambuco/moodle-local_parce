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
 * Resolved appearance settings for the chat window.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class appearance {
    /** Window anchored to the right-hand drawer. */
    public const PLACEMENT_DOCKED = 'docked';

    /** Floating window. */
    public const PLACEMENT_FLOATING = 'floating';

    /** Icon used when none is stored or the stored file is gone. */
    public const DEFAULT_ICON = 'robot';

    /**
     * Placement to render.
     *
     * @return string docked or floating
     */
    public static function get_placement(): string {
        $placement = get_config('local_parce', 'windowplacement');
        if ($placement === self::PLACEMENT_FLOATING) {
            return self::PLACEMENT_FLOATING;
        }
        return self::PLACEMENT_DOCKED;
    }

    /**
     * Whether the chat opens in the right-hand drawer.
     *
     * @return bool
     */
    public static function is_docked(): bool {
        return self::get_placement() === self::PLACEMENT_DOCKED;
    }

    /**
     * Icon file name to show on the open button.
     *
     * @return string
     */
    public static function get_icon(): string {
        $icon = get_config('local_parce', 'buttonicon');
        if (is_string($icon) && self::icon_exists($icon)) {
            return $icon;
        }
        return self::DEFAULT_ICON;
    }

    /**
     * Pix icon path for a file in pix/icons.
     *
     * @param string $icon File name without extension
     * @return string
     */
    public static function pix_name(string $icon): string {
        return 'icons/' . $icon;
    }

    /**
     * Visible label for an icon file name.
     *
     * @param string $name File name without extension
     * @return string
     */
    public static function icon_label(string $name): string {
        return str_replace(['-', '_'], ' ', $name);
    }

    /**
     * Icons available in pix/icons.
     *
     * @return array<string, string> File name => visible label
     */
    public static function get_icon_choices(): array {
        $choices = [];
        $files = glob(self::icons_directory() . '/*.svg') ?: [];
        foreach ($files as $file) {
            $name = basename($file, '.svg');
            if (!self::is_safe_icon_name($name)) {
                continue;
            }
            $choices[$name] = self::icon_label($name);
        }
        ksort($choices);
        return $choices;
    }

    /**
     * Whether pix/icons contains this SVG.
     *
     * @param string $name File name without extension
     * @return bool
     */
    public static function icon_exists(string $name): bool {
        if (!self::is_safe_icon_name($name)) {
            return false;
        }
        return is_readable(self::icons_directory() . '/' . $name . '.svg');
    }

    /**
     * Accept only a bare file name.
     *
     * @param string $name File name without extension
     * @return bool
     */
    private static function is_safe_icon_name(string $name): bool {
        return (bool) preg_match('/^[a-z0-9_-]+$/', $name);
    }

    /**
     * Directory that stores selectable button icons.
     *
     * @return string
     */
    private static function icons_directory(): string {
        global $CFG;
        return $CFG->dirroot . '/local/parce/pix/icons';
    }
}
