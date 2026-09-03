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

/**
 * Add the owner's history to their profile navigation.
 *
 * @param navigation_node $navigation User navigation
 * @param stdClass $user Profile owner
 * @param context_user $usercontext User context
 * @param stdClass $course Current course
 * @param context_course|null $coursecontext Course context
 */
function local_parce_extend_navigation_user($navigation, $user, $usercontext, $course, $coursecontext): void {
    global $USER;

    if (!isloggedin() || isguestuser() || (int) $user->id !== (int) $USER->id) {
        return;
    }
    $navigation->add(
        get_string('historytitle', 'local_parce'),
        new core\url('/local/parce/history.php'),
        navigation_node::TYPE_SETTING,
        null,
        'local_parce_history',
        new core\output\pix_icon('i/history', '')
    );
}

/**
 * Add the owner's history to user settings/navigation.
 *
 * @param navigation_node $navigation User settings navigation
 * @param stdClass $user User
 * @param context_user $usercontext User context
 * @param stdClass $course Current course
 * @param context_course|null $coursecontext Course context
 */
function local_parce_extend_navigation_user_settings($navigation, $user, $usercontext, $course, $coursecontext): void {
    local_parce_extend_navigation_user($navigation, $user, $usercontext, $course, $coursecontext);
}

/**
 * Add the administrative history entry to course navigation.
 *
 * @param navigation_node $navigation Course navigation
 * @param stdClass $course Course
 * @param context_course $context Course context
 */
function local_parce_extend_navigation_course($navigation, $course, $context): void {
    if (!isguestuser() && has_capability('local/parce:viewallchats', $context)) {
        $navigation->add(
            get_string('historyadminlink', 'local_parce'),
            new core\url('/local/parce/history.php', ['chatid' => $context->id, 'mode' => 'admin']),
            navigation_node::TYPE_SETTING,
            null,
            'local_parce_course_history',
            new core\output\pix_icon('i/history', '')
        );
    }
}
