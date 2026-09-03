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
 * Implementation of web service local_parce_answer
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class answer extends external_api {
    /**
     * Describes the parameters for local_parce_answer
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'question' => new external_value(PARAM_RAW, 'Question from the user'),
            'contextid' => new external_value(PARAM_INT, 'Current context', VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * Implementation of web service local_parce_answer
     *
     * Processes the user's question and returns an answer.
     *
     * @param string $question The question submitted by the user
     * @param int $contextid The context ID for capability checks and answer generation
     * @return array Array containing the answer
     */
    public static function execute(string $question, int $contextid = 1): array {
        global $USER;

        // Parameter validation.
        ['question' => $question, 'contextid' => $contextid] = self::validate_parameters(
            self::execute_parameters(),
            ['question' => $question, 'contextid' => $contextid]
        );

        // Get the context instance from the provided context ID.
        $context = \context::instance_by_id($contextid);
        self::validate_context($context);

        controller::require_chat_access($context);
        $chatcontext = controller::get_chat_context($context);
        controller::require_chat_access($chatcontext);

        $question = trim($question);
        if ($question === '') {
            throw new \invalid_parameter_exception(get_string('error_empty_question', 'local_parce'));
        }
        if (\core_text::strlen($question) > controller::MAX_QUESTION_LENGTH) {
            throw new \invalid_parameter_exception(get_string('error_question_too_long', 'local_parce'));
        }

        $chatid = $chatcontext->id;
        $snapshot = null;
        $newconversation = controller::prepare_conversation($USER->id, $chatid, $question, $snapshot);
        $preparationresolved = false;
        $cacheversion = controller::get_active_cache_version();

        try {
            // Process the question using the question handler.
            $answer = \local_parce\local\question_handler::process($question, $context);
            $result = \local_parce\local\question_handler::get_last_result();

            if (trim($answer) === 'NOT_FOUND') {
                $answer = get_string('answer_notfound', 'local_parce');
            }

            // Convert Markdown on the server and clean the resulting HTML.
            $answer = format_text($answer, FORMAT_MARKDOWN, [
                'noclean' => false,
                'para' => false,
                'filter' => false,
            ]);

            // Only store in conversation history when a successful response was generated.
            // Error/fallback messages pollute the context and bias the AI toward failure responses.
            if (
                \local_parce\local\question_handler::was_last_successful()
                && controller::is_active_cache_version($cacheversion)
            ) {
                controller::store_conversation(
                    userid: $USER->id,
                    chatid: $chatid,
                    question: $question,
                    response: $answer,
                    actionids: \local_parce\local\question_handler::get_last_action_ids(),
                    cacheversion: $cacheversion
                );
            } else {
                controller::restore_prepared_conversation($snapshot);
                $newconversation = false;
            }
            $preparationresolved = true;

            $usage = controller::get_conversation_usage($USER->id, $chatid);
            return [
                'answer' => $answer,
                'newconversation' => $newconversation,
                'usagepercentage' => $usage['percentage'],
            ] + $result;
        } finally {
            if (!$preparationresolved) {
                controller::restore_prepared_conversation($snapshot);
            }
        }
    }

    /**
     * Describe the return structure for local_parce_answer
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'answer' => new external_value(PARAM_RAW, 'The answer to the user question'),
            'newconversation' => new external_value(PARAM_BOOL, 'Whether this question started a new conversation'),
            'usagepercentage' => new external_value(PARAM_INT, 'Percentage of the active conversation limit used'),
            'status' => new external_value(PARAM_ALPHAEXT, 'Result discriminator: success, error or rate_limited'),
            'successful' => new external_value(PARAM_BOOL, 'Whether the operation completed successfully'),
            'retryable' => new external_value(PARAM_BOOL, 'Whether a later user-initiated retry may succeed'),
            'errorcode' => new external_value(PARAM_ALPHANUMEXT, 'Stable machine-readable error code', VALUE_OPTIONAL),
            'retryafter' => new external_value(PARAM_INT, 'Retry delay in seconds when known', VALUE_OPTIONAL),
        ]);
    }
}
