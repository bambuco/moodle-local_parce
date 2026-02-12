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
 * Local Parce - Question Handler Class
 *
 * Handles the processing of user questions and generates appropriate responses
 * using local_parce custom AI actions (answer_question, question_plan).
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_parce\local;

use local_parce\aiactions\question_plan;

/**
 * Question handler class
 *
 * This class is responsible for processing user questions and generating responses
 * using local_parce custom AI actions (answer_question, question_plan).
 */
class question_handler {
    /**
     * Process a question and return a response.
     *
     * @param string $question The question text from the user.
     * @param object $context The question context.
     * @return string The response to the question.
     */
    public static function process($question, $context = null): string {
        global $USER;

        // Validate input.
        if (empty($question)) {
            return get_string('error_empty_question', 'local_parce');
        }

        if (empty($context)) {
            $context = \context_system::instance();
        }

        try {
            // Get the AI manager from DI container.
            $manager = \core\di::get(\core_ai\manager::class);
            $providerrecord = $manager->get_provider_records(['provider' => 'aiprovider_bbco\provider', 'enabled' => 1]);

            if (empty($providerrecord)) {
                return get_string('error_ai_unavailable', 'local_parce');
            }

            $record = reset($providerrecord);
            $provider = new \aiprovider_bbco\provider(
                true,
                id: $record->id,
                name: $record->name,
                config: $record->config,
                actionconfig: $record->actionconfig,
            );

            if ($provider->is_provider_configured() === false) {
                return get_string('error_ai_unavailable', 'local_parce');
            }

            // Time 1: Get the real intention of the question.
            $hackquestion = new \stdClass();
            $hackquestion->question = $question;
            $hackquestion->previous = []; // ToDo: manejar el contexto de la conversación.
            $jsonquestion = json_encode($hackquestion);

            // Create the appropriate action with the user's question.
            $action = new question_plan(
                contextid: $context->id,
                userid: $USER->id,
                prompttext: $jsonquestion
            );

            $prompt = get_config('local_parce', 'question_plan_prompt');
            if (empty($prompt)) {
                $prompt = get_string('default_question_plan_prompt', 'local_parce');
            }

            $actionprovider = new \aiprovider_bbco\process_generate_text($provider, $action);
            $actionprovider->prompt = $prompt;
            $response = $actionprovider->process();

            $generatedcontent = '';
            // Check if successful.
            if ($response->get_success()) {
                $responsedata = $response->get_response_data();

                if (empty($responsedata['generatedcontent'])) {
                    return get_string('error_no_content', 'local_parce');
                }
                $generatedcontent = $responsedata['generatedcontent'];
            } else {
                return get_string('error_ai_failed', 'local_parce') . ': ' . $response->get_errormessage();
            }

            // Time 2: Get the response based on the intention.
            $type = @json_decode($generatedcontent, true);
            $intentclass = '\local_parce\local\intent\\' . $type['type'] ?? 'base';

            if (!class_exists($intentclass)) {
                return get_string('error_processing_question', 'local_parce');
            }

            $intentparams = $type['params'] ?? [];
            if (!is_array($intentparams)) {
                $intentparams = [$intentparams];
            }
            $intentobj = new $intentclass($context, null, $intentparams);

            if (!$intentobj->require_ia()) {
                return $intentobj->get_content();
            }

            try {
                $content = $intentobj->get_content();
            } catch (\Exception $e) {
                return $e->getMessage();
            }

            if (empty($content)) {
                $allowopenanswer = get_config('local_parce', 'allowopenanswer');
                if (!$allowopenanswer) {
                    return get_string('msg_no_content', 'local_parce');
                }

                $content = get_config('local_parce', 'openanswer_prompt');

                if (empty($content)) {
                    $content = get_string('default_openanswer_prompt', 'local_parce');
                }
            }

            $hackquestion = new \stdClass();
            $hackquestion->question = $question;
            $hackquestion->previous = [];
            $hackquestion->content = $content;
            $jsonquestion = json_encode($hackquestion);

            $action = new question_plan(
                contextid: $context->id,
                userid: $USER->id,
                prompttext: $jsonquestion
            );

            $actionprovider = new \aiprovider_bbco\process_generate_text($provider, $action);
            $actionprovider->prompt = get_config('local_parce', 'answer_question_prompt');
            if (empty($prompt)) {
                $prompt = get_string('default_answer_question_prompt', 'local_parce');
            }

            $response = $actionprovider->process();

            $generatedcontent = '';
            // Check if successful.
            if ($response->get_success()) {
                $responsedata = $response->get_response_data();

                if (empty($responsedata['generatedcontent'])) {
                    return get_string('error_no_content', 'local_parce');
                }
                $generatedcontent = $responsedata['generatedcontent'];
            } else {
                return get_string('error_ai_failed', 'local_parce') . ': ' . $response->get_errormessage();
            }

            return $generatedcontent;
        } catch (\core\exception\coding_exception $e) {
            return get_string('error_ai_unavailable', 'local_parce');
        } catch (\Exception $e) {
            return get_string('error_processing_question', 'local_parce');
        }
    }
}
