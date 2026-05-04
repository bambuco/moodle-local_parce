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

use core_analytics\site;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_api;
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
        global $USER, $DB;

        // Parameter validation.
        ['question' => $question, 'contextid' => $contextid] = self::validate_parameters(
            self::execute_parameters(),
            ['question' => $question, 'contextid' => $contextid]
        );

        // Get the context instance from the provided context ID.
        $context = \context::instance_by_id($contextid);
        self::validate_context($context);

        // Check if user has permission to use the chat.
        require_capability('local/parce:usechat', $context);

        // Process the question using the question handler.
        $answer = \local_parce\local\question_handler::process($question, $context);

        // Sanitize the answer to prevent XSS attacks
        // Strip dangerous tags and event handlers while preserving safe HTML.
        $answer = \core_text::entities_to_utf8($answer);
        $answer = strip_tags($answer);
        $answer = format_text($answer, FORMAT_HTML, [
            'noclean' => false,
            'para' => false,
            'filter' => false,
        ]);

        if (trim($answer) == 'NOT_FOUND') {
            $answer = get_string('answer_notfound', 'local_parce');
        }

        $coursecontext = $context->get_course_context(false);
        if ($coursecontext) {
            $chatid = $coursecontext->id;
        } else {
            $chatid = SITEID;
        }

        // Only store in conversation history when a successful response was generated.
        // Error/fallback messages pollute the context and bias the AI toward failure responses.
        if (\local_parce\local\question_handler::was_last_successful()) {
            $entryid = controller::store_conversation(
                userid: $USER->id,
                chatid: $chatid,
                question: $question,
                response: $answer
            );

            // Link the AI action records to this conversation entry.
            $actionids = \local_parce\local\question_handler::get_last_action_ids();
            foreach ($actionids as $actionid) {
                $DB->set_field('local_parce_ai_actions', 'conversationentryid', $entryid, ['id' => $actionid]);
            }
        }

        return [
            'answer' => $answer,
        ];
    }

    /**
     * Describe the return structure for local_parce_answer
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'answer' => new external_value(PARAM_RAW, 'The answer to the user question'),
        ]);
    }
}
