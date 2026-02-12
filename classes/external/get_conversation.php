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

namespace local_parce\external;

use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_api;
use core_external\external_value;

/**
 * Implementation of web service local_parce_get_conversation
 *
 * Retrieves paginated chat conversation history with permission validation.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_conversation extends external_api {
    /**
     * Describes the parameters for local_parce_get_conversation
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'chatid' => new external_value(PARAM_INT, 'Chat ID (typically course ID)'),
            'userid' => new external_value(
                PARAM_INT,
                'User ID (optional, defaults to current user)',
                VALUE_DEFAULT,
                0
            ),
            'offset' => new external_value(
                PARAM_INT,
                'Pagination offset for loading older messages',
                VALUE_DEFAULT,
                0
            ),
            'limit' => new external_value(
                PARAM_INT,
                'Number of entries to return per page (0 for all)',
                VALUE_DEFAULT,
                20
            ),
        ]);
    }

    /**
     * Implementation of web service local_parce_get_conversation
     *
     * Retrieves paginated conversation history with proper permission checks.
     * By default returns the current user's conversation. To retrieve another user's
     * conversation history, the current user must have local/parce:viewallchats capability.
     *
     * @param int $chatid The chat ID (typically course ID)
     * @param int $userid The user ID (0 = current user)
     * @param int $offset Pagination offset
     * @param int $limit Entries per page
     * @return array Array containing paginated entries and metadata
     * @throws moodle_exception If permission denied or invalid parameters
     */
    public static function execute(
        int $chatid,
        int $userid = 0,
        int $offset = 0,
        int $limit = 20
    ): array {
        global $USER;

        // Parameter validation.
        [
            'chatid' => $chatid,
            'userid' => $userid,
            'offset' => $offset,
            'limit' => $limit,
        ] = self::validate_parameters(
            self::execute_parameters(),
            [
                'chatid' => $chatid,
                'userid' => $userid,
                'offset' => $offset,
                'limit' => $limit,
            ]
        );

        // Validate context (system level for this operation).
        self::validate_context(\context_system::instance());

        // Determine target user ID.
        $targetuserid = ($userid > 0) ? $userid : $USER->id;

        // Permission validation: can only view own chat or have viewallchats capability.
        if ($targetuserid !== $USER->id) {
            require_capability('local/parce:viewallchats', \context_system::instance());
        }

        // Get paginated conversation entries.
        $result = \local_parce\local\controller::get_conversation_entries_paginated(
            $targetuserid,
            $chatid,
            $offset,
            $limit
        );

        // Format timestamps for display.
        foreach ($result['entries'] as &$entry) {
            $entry['timestamp_formatted'] = \local_parce\local\controller::format_timestamp(
                $entry['timestamp']
            );
        }

        return $result;
    }

    /**
     * Describe the return structure for local_parce_get_conversation
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'entries' => new external_multiple_structure(
                new external_single_structure([
                    'role' => new external_value(PARAM_ALPHA, 'Entry role: user or system'),
                    'content' => new external_value(PARAM_RAW, 'Message content'),
                    'timestamp' => new external_value(PARAM_INT, 'Unix timestamp'),
                    'timestamp_formatted' => new external_value(
                        PARAM_TEXT,
                        'Formatted timestamp for display (e.g., "14:30", "Yesterday 14:30", "15 Jan")'
                    ),
                ]),
                'Conversation entries with timestamps'
            ),
            'total' => new external_value(PARAM_INT, 'Total entries in this conversation'),
            'offset' => new external_value(PARAM_INT, 'Current pagination offset'),
            'limit' => new external_value(PARAM_INT, 'Limit used for this page'),
            'hasmore' => new external_value(PARAM_BOOL, 'Whether more entries exist beyond this page'),
        ]);
    }
}
