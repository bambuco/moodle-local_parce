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
     * @var int[] IDs of the AI action log records created during processing.
     */
    private static array $lastactionids = [];

    /**
     * @var bool Whether the last process() call produced a successful content response.
     */
    private static bool $lastsuccessful = false;

    /**
     * Get the AI action IDs from the last process() call.
     *
     * @return int[] Array of local_parce_ai_actions record IDs
     */
    public static function get_last_action_ids(): array {
        return self::$lastactionids;
    }

    /**
     * Check if the last process() call produced a successful content response.
     *
     * @return bool True if the last response was successful content, false if it was an error/fallback message.
     */
    public static function was_last_successful(): bool {
        return self::$lastsuccessful;
    }

    /**
     * Process a question and return a response.
     *
     * @param string $question The question text from the user.
     * @param object $context The question context.
     * @return string The response to the question.
     */
    public static function process($question, $context = null): string {
        global $USER;

        self::$lastactionids = [];
        self::$lastsuccessful = false;

        // Validate input.
        if (empty($question)) {
            return get_string('error_empty_question', 'local_parce');
        }

        $coursecontext = $context->get_course_context(false);
        if (empty($context) || empty($coursecontext)) {
            $context = \context_system::instance();
            $chatid = SITEID;
        } else {
            $chatid = $coursecontext->id;
        }

        try {
            // Manage static requirements.
            $helptext = get_string('static_help', 'local_parce');
            if (strtolower($question) === strtolower($helptext)) {
                $intentobj = new intent\help($context, null);
                self::$lastsuccessful = true;
                return self::pre_response($question, $intentobj->get_content());
            }

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

            $conversationkey = controller::generate_conversation_key($USER->id, $chatid);

            // Time 1: Get the real intention of the question.
            $previous = self::get_conversation_context($USER->id, $chatid);
            $hackquestion = self::make_hackquestion($question, $previous);

            // Create the appropriate action with the user's question.
            $action = new question_plan(
                contextid: $context->id,
                userid: $USER->id,
                prompttext: $hackquestion
            );

            $prompt = get_config('local_parce', 'question_plan_prompt');
            if (empty($prompt)) {
                $prompt = get_string('default_question_plan_prompt', 'local_parce');
            }

            $actionprovider = new \aiprovider_bbco\process_generate_text($provider, $action);
            $actionprovider->prompt = $prompt;
            $response = $actionprovider->process();

            // Log question_plan action.
            $planactionid = controller::log_ai_action(
                $USER->id,
                $context->id,
                $chatid,
                $conversationkey,
                'question_plan',
                $prompt,
                $hackquestion,
                $response
            );
            self::$lastactionids[] = $planactionid;

            $generatedcontent = '';
            // Check if successful.
            if ($response->get_success()) {
                $responsedata = $response->get_response_data();

                if (empty($responsedata['generatedcontent'])) {
                    return self::pre_response($question, get_string('error_no_content', 'local_parce'));
                }
                $generatedcontent = $responsedata['generatedcontent'];
            } else {
                return get_string('error_ai_failed', 'local_parce') . ': ' . $response->get_errormessage();
            }

            // Time 2: Get the response based on the intention.
            $type = @json_decode($generatedcontent, true);

            $intentavailable = ['base', 'content', 'dates', 'greeting', 'help'];
            if (empty($type) || !is_array($type) || empty($type['type']) || !in_array($type['type'], $intentavailable)) {
                return get_string('error_processing_question', 'local_parce');
            }

            $intentname = $type['type'];
            $intentparams = $type['params'] ?? [];
            if (!is_array($intentparams)) {
                $intentparams = [$intentparams];
            }

            // Update the plan action with the detected intent.
            controller::update_ai_action($planactionid, $intentname, $intentparams);

            $intentclass = '\local_parce\local\intent\\' . $intentname;

            if (!class_exists($intentclass)) {
                return get_string('error_processing_question', 'local_parce');
            }

            $intentobj = new $intentclass($context, null, $intentparams);

            if (!$intentobj->require_ia()) {
                self::$lastsuccessful = true;
                return self::pre_response($question, $intentobj->get_content());
            }

            try {
                $content = $intentobj->get_content();
            } catch (\Exception $e) {
                return self::pre_response($question, $e->getMessage());
            }

            if (empty($content)) {
                $allowopenanswer = get_config('local_parce', 'allowopenanswer');
                if (!$allowopenanswer) {
                    return self::pre_response($question, get_string('msg_no_content', 'local_parce'));
                }

                $content = get_config('local_parce', 'openanswer_prompt');

                if (empty($content)) {
                    $content = get_string('default_openanswer_prompt', 'local_parce');
                }
            }

            $previous = self::get_conversation_context($USER->id, $chatid);
            $hackquestion = self::make_hackquestion($question, $previous, $content);

            $action = new question_plan(
                contextid: $context->id,
                userid: $USER->id,
                prompttext: $hackquestion
            );

            $answerprompt = get_config('local_parce', 'answer_question_prompt');
            if (empty($answerprompt)) {
                $answerprompt = get_string('default_answer_question_prompt', 'local_parce');
            }

            $actionprovider = new \aiprovider_bbco\process_generate_text($provider, $action);
            $actionprovider->prompt = $answerprompt;

            $response = $actionprovider->process();

            // Log answer_question action.
            $answeractionid = controller::log_ai_action(
                $USER->id,
                $context->id,
                $chatid,
                $conversationkey,
                'answer_question',
                $answerprompt,
                $hackquestion,
                $response
            );
            self::$lastactionids[] = $answeractionid;

            // Set the intent on the answer action too.
            controller::update_ai_action($answeractionid, $intentname, $intentparams);

            $generatedcontent = '';
            // Check if successful.
            if ($response->get_success()) {
                $responsedata = $response->get_response_data();

                if (empty($responsedata['generatedcontent'])) {
                    return self::pre_response($question, get_string('error_no_content', 'local_parce'));
                }
                $generatedcontent = $responsedata['generatedcontent'];
            } else {
                return get_string('error_ai_failed', 'local_parce') . ': ' . $response->get_errormessage();
            }

            // Append course references if the content came from courses other than the current one.
            $generatedcontent .= self::build_course_references($content, $context);

            self::$lastsuccessful = true;
            return self::pre_response($question, $generatedcontent);
        } catch (\core\exception\coding_exception $e) {
            return get_string('error_ai_unavailable', 'local_parce');
        } catch (\Throwable $e) {
            return get_string('error_processing_question', 'local_parce');
        }
    }

    /**
     * Pre-process the AI response before returning it to the user.
     *
     * @param string $question The original question from the user.
     * @param string $response The raw response from the AI.
     * @return string The processed response to be returned to the user.
     */
    private static function pre_response(string $question, string $response): string {
        // Here you can add any pre-processing steps needed before returning the response.
        // For example, you could log the question and response, or perform additional formatting.
        return $response;
    }

    /**
     * Get conversation context from cache.
     *
     * Retrieves the last 5 messages from the cached conversation to provide
     * context for the AI model.
     *
     * @param int $userid The user ID
     * @param int $chatid The chat ID (course ID)
     * @return array Array of last 5 conversation entries with role and content
     */
    private static function get_conversation_context(int $userid, int $chatid): array {
        $allentries = controller::get_conversation_entries($userid, $chatid);

        if (empty($allentries)) {
            return [];
        }

        // Get last 5 entries, keeping them in chronological order.
        $lastfive = array_slice($allentries, -5);

        // Format for AI context: only include role and content.
        $context = [];
        foreach ($lastfive as $entry) {
            $context[] = [
                'role' => $entry['role'],
                'content' => $entry['content'],
            ];
        }

        return $context;
    }

    /**
     * Create a hack question format to send to the AI model, including the original question,
     * previous conversation context, and any relevant content.
     *
     * This format allows the AI model to better understand the user's question in the context
     * of the conversation and any relevant information, improving the quality of the response.
     *
     * @param string $question The original question from the user.
     * @param array $previous The previous conversation context, formatted as an array of role/content pairs.
     * @param string $content Any relevant content that should be included for the AI to generate a response.
     * @return string The formatted hack question to send to the AI model.
     */
    private static function make_hackquestion(string $question, array $previous = [], string $content = ''): string {
        $hackquestion = '<QUESTION_START>' . $question . '<QUESTION_END>';

        if (!empty($previous)) {
            $previoustext = '';
            foreach ($previous as $entry) {
                $previoustext .= '<ROLE_START>' . $entry['role'] . '<ROLE_END>';
                $previoustext .= '<MESSAGE_START>' . $entry['content'] . '<MESSAGE_END>';
            }
            $hackquestion .= '<PREVIOUS_START>' . $previoustext . '<PREVIOUS_END>';
        }

        if (!empty($content)) {
            $hackquestion .= '<CONTENT_START>' . $content . '<CONTENT_END>';
        }

        return $hackquestion;
    }

    /**
     * Build markdown course references from the search results JSON.
     *
     * Extracts unique course names/URLs from the content JSON and returns
     * a markdown-formatted line for each course, excluding the current context course.
     *
     * @param string $contentjson The JSON string with search results containing coursename and courseurl fields.
     * @param \core\context $context The current context to determine if the course reference is needed.
     * @return string Markdown-formatted course references or empty string.
     */
    private static function build_course_references(string $contentjson, \core\context $context): string {
        $items = @json_decode($contentjson, true);
        if (empty($items) || !is_array($items)) {
            return '';
        }

        $currentcourseid = 0;
        $coursecontext = $context->get_course_context(false);
        if (!empty($coursecontext)) {
            $currentcourseid = $coursecontext->instanceid;
        }

        // Collect unique courses that differ from the current one.
        $courses = [];
        foreach ($items as $item) {
            if (empty($item['coursename']) || empty($item['courseurl'])) {
                continue;
            }
            $url = $item['courseurl'];
            if (isset($courses[$url])) {
                continue;
            }
            // Skip the site-level course and the current course context.
            if (preg_match('/[?&]id=(\d+)/', $url, $matches)) {
                $courseid = (int)$matches[1];
                if ($courseid === SITEID || $courseid === $currentcourseid) {
                    continue;
                }
            }
            $courses[$url] = $item['coursename'];
        }

        if (empty($courses)) {
            return '';
        }

        $lines = "\n\n---\n";
        foreach ($courses as $url => $name) {
            $lines .= '📚 ' . get_string('course_reference', 'local_parce', [
                'coursename' => $name,
                'courseurl' => $url,
            ]) . "\n";
        }

        return $lines;
    }
}
