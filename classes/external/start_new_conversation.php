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
use core_external\external_single_structure;
use core_external\external_value;
use local_parce\local\controller;

/**
 * Start an empty active conversation.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class start_new_conversation extends external_api {
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
     * Archive the active thread and open an empty one with the page context.
     *
     * @param int $chatid Canonical chat context ID
     * @return array Rotation flag, context choice and usage
     */
    public static function execute(int $chatid): array {
        global $USER;

        ['chatid' => $chatid] = self::validate_parameters(
            self::execute_parameters(),
            ['chatid' => $chatid]
        );

        $context = \context::instance_by_id($chatid);
        self::validate_context($context);
        controller::require_chat_context($context);
        controller::require_chat_access($context);

        $rotated = controller::start_new_conversation($USER->id, $context->id);
        $usage = controller::get_conversation_usage($USER->id, $context->id);
        return [
            'rotated' => $rotated,
            'globalcontext' => controller::get_global_context($USER->id, $context->id),
            'usagepercentage' => $usage['percentage'],
        ];
    }

    /**
     * Describe return data.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'rotated' => new external_value(PARAM_BOOL, 'Whether a previous active thread was replaced'),
            'globalcontext' => new external_value(PARAM_BOOL, 'Whether the new conversation uses the site context'),
            'usagepercentage' => new external_value(PARAM_INT, 'Percentage of the active conversation limit used'),
        ]);
    }
}
