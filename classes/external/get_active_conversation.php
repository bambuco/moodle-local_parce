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

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_parce\local\controller;

/**
 * Return the conversation active in the current session cache.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_active_conversation extends external_api {
    /**
     * Describe parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'chatid' => new external_value(PARAM_INT, 'Canonical chat context ID'),
        ]);
    }

    /**
     * Return the active conversation.
     *
     * @param int $chatid Canonical chat context ID
     * @return array
     */
    public static function execute(int $chatid): array {
        global $USER;

        ['chatid' => $chatid] = self::validate_parameters(
            self::execute_parameters(),
            ['chatid' => $chatid]
        );

        $context = \context::instance_by_id($chatid);
        self::validate_context($context);
        self::validate_chat_context($context);
        controller::require_chat_access($context);

        $entries = controller::get_conversation_entries($USER->id, $context->id);
        foreach ($entries as &$entry) {
            $entry['timestamp_formatted'] = controller::format_timestamp($entry['timestamp']);
        }
        unset($entry);

        $usage = controller::get_conversation_usage($USER->id, $context->id);
        return [
            'entries' => $entries,
            'total' => count($entries),
            'usagepercentage' => $usage['percentage'],
        ];
    }

    /**
     * Ensure only canonical course and system contexts are accepted.
     *
     * @param \context $context Context to validate
     */
    private static function validate_chat_context(\context $context): void {
        if (!in_array($context->contextlevel, [CONTEXT_SYSTEM, CONTEXT_COURSE], true)) {
            throw new \invalid_parameter_exception('The chat ID is not a valid chat context.');
        }
    }

    /**
     * Describe return data.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'entries' => new external_multiple_structure(new external_single_structure([
                'role' => new external_value(PARAM_ALPHA, 'Entry role'),
                'content' => new external_value(PARAM_RAW, 'Message content'),
                'timestamp' => new external_value(PARAM_INT, 'Unix timestamp'),
                'timestamp_formatted' => new external_value(PARAM_TEXT, 'Formatted timestamp'),
            ])),
            'total' => new external_value(PARAM_INT, 'Total messages'),
            'usagepercentage' => new external_value(PARAM_INT, 'Percentage of the active conversation limit used'),
        ]);
    }
}
