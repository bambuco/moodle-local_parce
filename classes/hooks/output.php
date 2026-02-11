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

/**
 * Hook callbacks for output-related events
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_parce\hooks;

use core\hook\output\before_footer_html_generation;

/**
 * Hook callbacks for output events
 */
class output {

    /**
     * Inject the chat bubble HTML before footer generation.
     *
     * @param before_footer_html_generation $hook The hook object
     */
    public static function inject_chat_bubble(before_footer_html_generation $hook): void {
        global $PAGE, $COURSE;

        // Check if the plugin is enabled. Default to enabled if not configured.
        $enabled = get_config('local_parce', 'enabled');
        if (!$enabled) {
            return;
        }

        // Check user permissions.
        $allowguests = get_config('local_parce', 'enable_guests');
        if (!isloggedin() || (isguestuser() && !$allowguests)) {
            return;
        }

        if ($COURSE->id != SITEID) {
            // Check capability.
            $context = \context_course::instance($COURSE->id);
            if (!has_capability('local/parce:usechat', $context)) {
                return;
            }
        }

        // Inject the chat bubble HTML directly.
        $renderer = $PAGE->get_renderer('local_parce');
        $renderdata = [
            'chat_title' => get_config('local_parce', 'chat_title') ?? get_string('defaulttitle', 'local_parce'),
        ];
        $html = $renderer->render_from_template('local_parce/chat_bubble', $renderdata);
        $PAGE->requires->js_call_amd('local_parce/chat', 'init');

        $hook->add_html($html);
    }
}
