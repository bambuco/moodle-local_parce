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
 * Store the site-context choice for an empty conversation.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_global_context extends external_api {
    /**
     * Describe parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'chatid' => new external_value(PARAM_INT, 'Canonical chat context ID'),
            'enabled' => new external_value(PARAM_BOOL, 'Whether the empty conversation uses the site context'),
        ]);
    }

    /**
     * Store the choice when the conversation has not started.
     *
     * @param int $chatid Canonical chat context ID
     * @param bool $enabled Whether the empty conversation uses the site context
     * @return array Effective choice and whether this request changed it
     */
    public static function execute(int $chatid, bool $enabled): array {
        global $USER;

        ['chatid' => $chatid, 'enabled' => $enabled] = self::validate_parameters(
            self::execute_parameters(),
            ['chatid' => $chatid, 'enabled' => $enabled]
        );

        $context = \context::instance_by_id($chatid);
        self::validate_context($context);
        controller::require_chat_context($context);
        controller::require_chat_access($context);

        $accepted = controller::set_global_context($USER->id, $context->id, $enabled);
        return [
            'accepted' => $accepted,
            'globalcontext' => controller::get_global_context($USER->id, $context->id),
        ];
    }

    /**
     * Describe return data.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'accepted' => new external_value(PARAM_BOOL, 'Whether the requested choice was stored'),
            'globalcontext' => new external_value(PARAM_BOOL, 'Effective site-context choice'),
        ]);
    }
}
