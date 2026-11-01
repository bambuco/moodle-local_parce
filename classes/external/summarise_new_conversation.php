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
 * Open a conversation whose first message summarises the active one.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class summarise_new_conversation extends external_api {
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
     * Summarise the active conversation and start a new thread with that paragraph.
     *
     * The current thread is left unchanged when the summary cannot be produced.
     *
     * @param int $chatid Canonical chat context ID
     * @return array Summary message, context choice and usage
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

        $entries = controller::get_conversation_entries($USER->id, $context->id);
        $usage = controller::get_conversation_usage($USER->id, $context->id);
        if ($entries === []) {
            return self::failure($context->id, $USER->id, 'error_summary_empty', $usage['percentage']);
        }

        $conversation = controller::get_conversation($USER->id, $context->id);
        $inherited = controller::get_global_context($USER->id, $context->id);
        if ($inherited && !controller::can_use_chat(\context_system::instance())) {
            $inherited = false;
        }
        if (controller::is_site_level($context)) {
            $inherited = false;
        }

        $cacheversion = controller::get_active_cache_version();
        $summary = \local_parce\local\question_handler::summarise(
            $entries,
            $context,
            $context->id,
            (string) ($conversation['conversationkey'] ?? ''),
        );
        if (!$summary['successful'] || !controller::is_active_cache_version($cacheversion)) {
            $usage = controller::get_conversation_usage($USER->id, $context->id);
            return self::failure($context->id, $USER->id, 'error_summary_unavailable', $usage['percentage']);
        }

        $summaryhtml = format_text($summary['summary'], FORMAT_PLAIN, [
            'noclean' => false,
            'para' => true,
            'filter' => false,
        ]);
        $stored = controller::store_opening_summary(
            $USER->id,
            $context->id,
            $inherited,
            $summaryhtml,
            $summary['actionids'],
            $cacheversion
        );
        if (!$stored) {
            $usage = controller::get_conversation_usage($USER->id, $context->id);
            return self::failure($context->id, $USER->id, 'error_summary_unavailable', $usage['percentage']);
        }

        $usage = controller::get_conversation_usage($USER->id, $context->id);
        return [
            'successful' => true,
            'summary' => $summaryhtml,
            'globalcontext' => controller::get_global_context($USER->id, $context->id),
            'usagepercentage' => $usage['percentage'],
            'errormessage' => '',
        ];
    }

    /**
     * Describe return data.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'successful' => new external_value(PARAM_BOOL, 'Whether the summary started a new conversation'),
            'summary' => new external_value(PARAM_RAW, 'Sanitised summary paragraph, empty when unsuccessful'),
            'globalcontext' => new external_value(PARAM_BOOL, 'Whether the active conversation uses the site context'),
            'usagepercentage' => new external_value(PARAM_INT, 'Percentage of the active conversation limit used'),
            'errormessage' => new external_value(PARAM_TEXT, 'Message shown when the summary was not created'),
        ]);
    }

    /**
     * Return the active conversation unchanged.
     *
     * @param int $chatid Canonical chat context ID
     * @param int $userid User ID
     * @param string $stringid Language string for the failure
     * @param int $usagepercentage Current usage percentage
     * @return array
     */
    private static function failure(int $chatid, int $userid, string $stringid, int $usagepercentage): array {
        return [
            'successful' => false,
            'summary' => '',
            'globalcontext' => controller::get_global_context($userid, $chatid),
            'usagepercentage' => $usagepercentage,
            'errormessage' => get_string($stringid, 'local_parce'),
        ];
    }
}
