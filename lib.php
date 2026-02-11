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
 * Local Parce - Q&A Chat component hooks and main functions
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Hook called when the page is initialized.
 * Injects the chat widget resources into all pages.
 *
 * @param moodle_page $page The page object
 */
function local_parce_page_init(moodle_page $page) {
    global $USER;

    // Check if the plugin is enabled. Default to enabled if not configured.
    $enabled = get_config('local_parce', 'enabled');
    if ($enabled === false) {
        $enabled = 1; // Default enabled
    }
    if (!$enabled) {
        return;
    }

    // Check user permissions.
    $allow_guests = get_config('local_parce', 'enable_guests');
    if (!isloggedin() || (isguestuser() && !$allow_guests)) {
        return;
    }

    // Add jQuery requirement.
    $page->requires->jquery();

    // CSS is injected directly in the hook, no need to load here.
    // Initialize the chat module.
    $page->requires->js_call_amd('local_parce/chat', 'init', []);
}
