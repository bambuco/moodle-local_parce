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
     * Load marked.js library before HTTP headers are sent.
     * This ensures the library is available for AMD modules.
     *
     * @param \core\hook\before_http_headers $hook The hook object
     */
    public static function before_http_headers(\core\hook\output\before_http_headers $hook): void {
        global $PAGE, $COURSE;

        if (!\local_parce\local\controller::chat_include()) {
            return;
        }

        // Load marked.js library for markdown parsing.
        $PAGE->requires->js(new \moodle_url('/local/parce/lib/marked/marked.min.js'), true);

        // Initialize the chat module.
        $PAGE->requires->js_call_amd('local_parce/chat', 'init', [$COURSE->id]);
    }

    /**
     * Inject the chat bubble HTML before footer generation.
     *
     * @param before_footer_html_generation $hook The hook object
     */
    public static function inject_chat_bubble(before_footer_html_generation $hook): void {
        global $PAGE;

        if (!\local_parce\local\controller::chat_include()) {
            return;
        }

        // Inject the chat bubble HTML directly.
        $renderer = $PAGE->get_renderer('local_parce');
        $renderdata = [
            'chat_title' => get_config('local_parce', 'chat_title') ?? get_string('defaulttitle', 'local_parce'),
        ];
        $html = $renderer->render_from_template('local_parce/chat_bubble', $renderdata);

        $hook->add_html($html);
    }
}
