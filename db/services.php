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
 * External functions and service declaration for Parce - Q&A Chat Widget
 *
 * Documentation: {@link https://moodledev.io/docs/apis/subsystems/external/description}
 *
 * @package    local_parce
 * @category   webservice
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_parce_answer' => [
        'classname' => local_parce\external\answer::class,
        'description' => 'Answer a question and return a structured success, error, or rate-limit result.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/parce:usechat',
    ],

    'local_parce_get_active_conversation' => [
        'classname' => local_parce\external\get_active_conversation::class,
        'description' => 'Get the complete current session conversation from cache.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/parce:usechat',
    ],

    'local_parce_get_conversation' => [
        'classname' => local_parce\external\get_conversation::class,
        'description' => 'Get persistent paginated chat conversation history.',
        'type' => 'read',
        'ajax' => true,
    ],

    'local_parce_list_history_contexts' => [
        'classname' => local_parce\external\list_history_contexts::class,
        'description' => 'List contexts containing persistent conversation history.',
        'type' => 'read',
        'ajax' => true,
    ],

    'local_parce_list_history_conversations' => [
        'classname' => local_parce\external\list_history_conversations::class,
        'description' => 'List persistent conversations in a context.',
        'type' => 'read',
        'ajax' => true,
    ],

    'local_parce_get_history_turns' => [
        'classname' => local_parce\external\get_history_turns::class,
        'description' => 'Get turns from one persistent conversation.',
        'type' => 'read',
        'ajax' => true,
    ],

    'local_parce_search_history' => [
        'classname' => local_parce\external\search_history::class,
        'description' => 'Search visible persistent conversation content by complete phrase.',
        'type' => 'read',
        'ajax' => true,
    ],
];
