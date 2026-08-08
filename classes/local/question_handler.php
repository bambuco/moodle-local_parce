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

    /** @var int[] IDs created by the most recent logical AI call. */
    private static array $lastcallids = [];

    /**
     * @var bool Whether the last process() call produced a successful content response.
     */
    private static bool $lastsuccessful = false;

    /**
     * @var array Structured outcome of the last process() call.
     */
    private static array $lastresult = [
        'status' => 'error',
        'successful' => false,
        'retryable' => true,
        'errorcode' => 'processing_error',
    ];

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
     * Get the structured outcome of the last process() call.
     *
     * @return array Status, success and retry metadata. retryafter is present only when known.
     */
    public static function get_last_result(): array {
        return self::$lastresult;
    }

    /**
     * Resolve the configured BBCO provider for Parce.
     *
     * @return \core_ai\provider|null The configured BBCO provider or null when unavailable.
     */
    public static function resolve_ai_provider(): ?\core_ai\provider {
        return (new ai_gateway())->resolve_provider();
    }

    /**
     * Process a question and return a response.
     *
     * @param string $question The question text from the user.
     * @param object $context The question context.
     * @param ai_gateway|null $gateway Optional test gateway.
     * @return string The response to the question.
     */
    public static function process($question, $context = null, ?ai_gateway $gateway = null): string {
        global $USER;

        self::$lastactionids = [];
        self::$lastsuccessful = false;
        self::$lastresult = self::failure_result('processing_error', true);
        $cacheversion = controller::get_active_cache_version();

        // Validate input.
        if (empty($question)) {
            return self::failure_response('error_empty_question', 'invalid_question', false);
        }

        $chatid = controller::get_chat_context($context)->id;

        try {
            // Manage static requirements.
            $helptext = get_string('static_help', 'local_parce');
            if (strtolower($question) === strtolower($helptext)) {
                $intentobj = new intent\help($context, null);
                return self::success_response($question, $intentobj->get_content());
            }

            $conversationkey = controller::generate_conversation_key($USER->id, $chatid);
            $requestid = bin2hex(random_bytes(32));
            $gateway = $gateway ?? new ai_gateway();

            // Time 1: Get the real intention of the question.
            $previous = self::get_conversation_context($USER->id, $chatid);
            $prompt = get_config('local_parce', 'question_plan_prompt');
            if (empty($prompt)) {
                $prompt = get_string('default_question_plan_prompt', 'local_parce');
            }
            $resourcetypes = intent\resource::get_module_type_catalogue($context);
            $resourcetypes = empty($resourcetypes) ? '' : json_encode($resourcetypes, JSON_UNESCAPED_SLASHES);
            $hackquestion = controller::build_ai_payload($question, $previous, '', $resourcetypes);
            $action = new question_plan(
                contextid: $context->id,
                userid: $USER->id,
                prompttext: $hackquestion
            );

            $generation = self::traced_generate(
                $gateway,
                $action,
                $USER->id,
                $context->id,
                $chatid,
                $conversationkey,
                $requestid,
                'question_plan',
                $prompt,
                $hackquestion,
                $cacheversion,
                function ($response): string {
                    if (!$response->get_success()) {
                        if ($response->get_errorcode() === 429) {
                            return 'rate_limited';
                        }
                        return self::is_timeout_message($response->get_errormessage()) ? 'timeout' : 'provider_error';
                    }
                    $content = $response->get_response_data()['generatedcontent'] ?? '';
                    if ($content === '') {
                        return 'empty_response';
                    }
                    $decoded = json_decode($content, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return 'invalid_json';
                    }
                    $valid = ['base', 'content', 'dates', 'grades', 'greeting', 'help', 'progress', 'resource'];
                    return is_array($decoded) && isset($decoded['type']) && in_array($decoded['type'], $valid, true)
                        ? 'success' : 'invalid_intent';
                }
            );
            if ($generation === null) {
                return self::failure_response('error_ai_unavailable', 'ai_unavailable', true);
            }
            $response = $generation['response'];
            if (!controller::is_active_cache_version($cacheversion)) {
                return self::failure_response('error_processing_question', 'request_cancelled', true);
            }
            $planactionid = self::$lastcallids[0];

            $generatedcontent = '';
            // Check if successful.
            if ($response->get_success()) {
                $responsedata = $response->get_response_data();

                if (empty($responsedata['generatedcontent'])) {
                    return self::failure_response('error_no_content', 'planning_empty', true);
                }
                $generatedcontent = $responsedata['generatedcontent'];
            } else {
                return self::provider_failure_response($response, 'planning_failed');
            }

            // Time 2: Get the response based on the intention.
            $type = @json_decode($generatedcontent, true);

            $intentavailable = ['base', 'content', 'dates', 'grades', 'greeting', 'help', 'progress', 'resource'];
            if (empty($type) || !is_array($type) || empty($type['type']) || !in_array($type['type'], $intentavailable)) {
                return self::failure_response('error_processing_question', 'invalid_intent', true);
            }

            $intentname = $type['type'];
            $intentparams = $type['params'] ?? [];
            if (!is_array($intentparams)) {
                $intentparams = [$intentparams];
            }

            // Update the plan action with the detected intent.
            foreach (self::$lastcallids as $actionid) {
                controller::update_ai_action($actionid, $intentname, $intentparams);
            }

            $intentclass = '\local_parce\local\intent\\' . $intentname;

            if (!class_exists($intentclass)) {
                return self::failure_response('error_processing_question', 'invalid_intent', true);
            }

            $intentobj = new $intentclass($context, null, $intentparams);

            try {
                $content = $intentobj->get_content();
            } catch (\moodle_exception $e) {
                $notfounderrors = [
                    'intent_content_notfound',
                    'intent_dates_notfound',
                    'intent_grades_notfound',
                    'intent_progress_notfound',
                    'intent_resource_notfound',
                ];
                if (in_array($e->errorcode, $notfounderrors, true)) {
                    return self::success_response($question, $e->getMessage(), false);
                }
                return self::failure_response('error_processing_question', 'content_error', true, $e->getMessage());
            } catch (\Throwable $e) {
                return self::failure_response('error_processing_question', 'content_error', true, $e->getMessage());
            }

            if (!$intentobj->require_ia()) {
                return self::success_response($question, $content);
            }

            // Course and similar search records may only contain a name and URL. Return those directly even if an
            // older or customised planning prompt classified the navigational request as content.
            if ($intentname === 'content' && intent\content::are_link_only_results($content)) {
                $resources = intent\content::format_search_results($content, 'resource_results');
                if ($resources !== '') {
                    return self::success_response($question, $resources);
                }
            }

            if (empty($content)) {
                $allowopenanswer = get_config('local_parce', 'allowopenanswer');
                if (!$allowopenanswer) {
                    return self::success_response($question, get_string('msg_no_content', 'local_parce'), false);
                }

                $content = get_config('local_parce', 'openanswer_prompt');

                if (empty($content)) {
                    $content = get_string('default_openanswer_prompt', 'local_parce');
                }
            }

            $previous = self::get_conversation_context($USER->id, $chatid);
            $answerprompt = get_config('local_parce', 'answer_question_prompt');
            if (empty($answerprompt)) {
                $answerprompt = get_string('default_answer_question_prompt', 'local_parce');
            }
            $hackquestion = controller::build_ai_payload($question, $previous, $content);
            $action = new question_plan(
                contextid: $context->id,
                userid: $USER->id,
                prompttext: $hackquestion
            );

            $generation = self::traced_generate(
                $gateway,
                $action,
                $USER->id,
                $context->id,
                $chatid,
                $conversationkey,
                $requestid,
                'answer_question',
                $answerprompt,
                $hackquestion,
                $cacheversion,
                function ($response): string {
                    if (!$response->get_success()) {
                        if ($response->get_errorcode() === 429) {
                            return 'rate_limited';
                        }
                        return self::is_timeout_message($response->get_errormessage()) ? 'timeout' : 'provider_error';
                    }
                    return empty($response->get_response_data()['generatedcontent']) ? 'empty_response' : 'success';
                }
            );
            if ($generation === null) {
                return self::failure_response('error_ai_unavailable', 'ai_unavailable', true);
            }
            $response = $generation['response'];
            if (!controller::is_active_cache_version($cacheversion)) {
                return self::failure_response('error_processing_question', 'request_cancelled', true);
            }

            // Set the intent on the answer action too.
            foreach (self::$lastcallids as $actionid) {
                controller::update_ai_action($actionid, $intentname, $intentparams);
            }

            $generatedcontent = '';
            // Check if successful.
            if ($response->get_success()) {
                $responsedata = $response->get_response_data();

                if (empty($responsedata['generatedcontent'])) {
                    return self::failure_response('error_no_content', 'response_empty', true);
                }
                $generatedcontent = $responsedata['generatedcontent'];
            } else {
                return self::provider_failure_response($response, 'response_failed');
            }

            // NOT_FOUND is an internal provider sentinel and must never be displayed or decorated with references.
            if (trim($generatedcontent) === 'NOT_FOUND') {
                $suggestions = intent\content::format_search_results($content, 'content_suggestions');
                if ($suggestions !== '') {
                    return self::success_response($question, $suggestions);
                }
                return self::success_response($question, get_string('answer_notfound', 'local_parce'), false);
            }

            // Append course references if the content came from courses other than the current one.
            $generatedcontent .= self::build_course_references($content, $context, $generatedcontent);

            return self::success_response($question, $generatedcontent);
        } catch (\core\exception\coding_exception $e) {
            return self::failure_response('error_ai_unavailable', 'ai_unavailable', true, $e->getMessage());
        } catch (\Throwable $e) {
            return self::failure_response('error_processing_question', 'processing_error', true, $e->getMessage());
        }
    }

    /**
     * Execute one logical AI call with a trace that is closed on every path.
     *
     * @return array|null Gateway result, or null when no provider is available
     */
    private static function traced_generate(
        ai_gateway $gateway,
        object $action,
        int $userid,
        int $contextid,
        int $chatid,
        string $conversationkey,
        string $requestid,
        string $actiontype,
        string $prompt,
        string $prompttext,
        int $cacheversion,
        callable $classify
    ): ?array {
        $callid = bin2hex(random_bytes(32));
        $actionid = controller::start_ai_action(
            $userid,
            $contextid,
            $chatid,
            $conversationkey,
            $requestid,
            $callid,
            $actiontype,
            $prompt,
            $prompttext
        );
        $generation = [];
        $response = null;
        $outcome = 'exception';
        $technical = null;
        $callstarted = hrtime(true);
        try {
            $provider = $gateway->resolve_provider();
            if ($provider === null) {
                $outcome = 'no_provider';
                return null;
            }
            $generation['providerattempted'] = true;
            $generation = $gateway->generate($provider, $action, $prompt);
            $generation['providerattempted'] = true;
            $generation['durationms'] ??= max(1, (int) ceil((hrtime(true) - $callstarted) / 1_000_000));
            $response = $generation['response'];
            $outcome = controller::is_active_cache_version($cacheversion) ? $classify($response) : 'request_cancelled';
            return $generation;
        } catch (\Throwable $e) {
            $technical = $e->getMessage();
            $outcome = self::is_timeout_message($technical) ? 'timeout' : 'exception';
            throw $e;
        } finally {
            $generation['durationms'] ??= max(1, (int) ceil((hrtime(true) - $callstarted) / 1_000_000));
            $ids = controller::complete_ai_action($actionid, $outcome, $generation, $response, $technical);
            self::$lastcallids = $ids;
            self::$lastactionids = array_merge(self::$lastactionids, $ids);
        }
    }

    /**
     * Identify timeout failures without exposing their technical message publicly.
     *
     * @param string|null $message Provider or exception detail
     * @return bool
     */
    private static function is_timeout_message(?string $message): bool {
        return $message !== null && preg_match('/\b(?:timed?\s*out|timeout)\b/i', $message) === 1;
    }

    /**
     * Record a successful result and return its display text.
     *
     * @param string $question Original question
     * @param string $response Display response
     * @param bool $hascontent Whether the response contains useful content that should be retained in the conversation.
     * @return string
     */
    private static function success_response(string $question, string $response, bool $hascontent = true): string {
        self::$lastsuccessful = $hascontent;
        self::$lastresult = [
            'status' => 'success',
            'successful' => true,
            'retryable' => false,
        ];
        return self::pre_response($question, $response);
    }

    /**
     * Convert a provider error to the public operational contract.
     *
     * @param \core_ai\aiactions\responses\response_base $response Provider response
     * @param string $errorcode Stable stage-specific error code
     * @return string
     */
    private static function provider_failure_response(
        \core_ai\aiactions\responses\response_base $response,
        string $errorcode
    ): string {
        $providercode = $response->get_errorcode();
        if ($providercode === 429) {
            return self::failure_response(
                'error_rate_limited',
                'rate_limited',
                true,
                $response->get_errormessage(),
                'rate_limited'
            );
        }

        $retryable = $providercode >= 500 && $providercode <= 599;
        return self::failure_response('error_ai_failed', $errorcode, $retryable, $response->get_errormessage());
    }

    /**
     * Build and record a safe failure response.
     *
     * @param string $stringid Generic language string identifier
     * @param string $errorcode Stable machine-readable code
     * @param bool $retryable Whether a later user-initiated retry may succeed
     * @param string|null $technical Technical detail
     * @param string $status Result discriminator
     * @return string
     */
    private static function failure_response(
        string $stringid,
        string $errorcode,
        bool $retryable,
        ?string $technical = null,
        string $status = 'error'
    ): string {
        self::$lastresult = self::failure_result($errorcode, $retryable, $status);
        return get_string($stringid, 'local_parce');
    }

    /**
     * Create failure metadata without inventing a retry-after value.
     *
     * @param string $errorcode Stable machine-readable code
     * @param bool $retryable Whether a later user-initiated retry may succeed
     * @param string $status Result discriminator
     * @return array
     */
    private static function failure_result(string $errorcode, bool $retryable, string $status = 'error'): array {
        return [
            'status' => $status,
            'successful' => false,
            'retryable' => $retryable,
            'errorcode' => $errorcode,
        ];
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
     * Retrieves recent complete turns from the active cached conversation,
     * subject to the prompt turn and estimated-token budgets.
     *
     * @param int $userid The user ID
     * @param int $chatid The chat ID (course ID)
     * @return array Recent complete conversation entries with role and content
     */
    private static function get_conversation_context(int $userid, int $chatid): array {
        return controller::get_prompt_context($userid, $chatid);
    }

    /**
     * Build markdown source references from the search results JSON.
     *
     * Uses each result's specific name and URL. The containing course is only a fallback when the result does not
     * provide its own link, and the current course is excluded from those fallback references.
     *
     * @param string $contentjson The JSON string with retrieved search results.
     * @param \core\context $context The current context to determine if the course reference is needed.
     * @param string $response Generated response in which references may already appear.
     * @return string Markdown-formatted course references or empty string.
     */
    private static function build_course_references(
        string $contentjson,
        \core\context $context,
        string $response
    ): string {
        $items = @json_decode($contentjson, true);
        if (empty($items) || !is_array($items)) {
            return '';
        }
        $response = html_entity_decode($response, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $currentcourseid = 0;
        $coursecontext = $context->get_course_context(false);
        if (!empty($coursecontext)) {
            $currentcourseid = $coursecontext->instanceid;
        }

        // Collect unique source resources, falling back to their containing course only when necessary.
        $sources = [];
        foreach ($items as $item) {
            $usescoursefallback = empty($item['name']) || empty($item['url']);
            $name = $usescoursefallback ? ($item['coursename'] ?? '') : $item['name'];
            $url = $usescoursefallback ? ($item['courseurl'] ?? '') : $item['url'];
            if ($name === '' || $url === '') {
                continue;
            }
            $decodedurl = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (isset($sources[$decodedurl]) || strpos($response, $decodedurl) !== false) {
                continue;
            }
            // A fallback link to the current or site course does not identify the actual source.
            if ($usescoursefallback && preg_match('/[?&]id=(\d+)/', $decodedurl, $matches)) {
                $courseid = (int)$matches[1];
                if ($courseid === SITEID || $courseid === $currentcourseid) {
                    continue;
                }
            }
            $sources[$decodedurl] = ['name' => $name, 'url' => $url];
        }

        if (empty($sources)) {
            return '';
        }

        $lines = "\n\n---\n";
        foreach ($sources as $source) {
            $lines .= '- 📚 ' . get_string('course_reference', 'local_parce', [
                'coursename' => $source['name'],
                'courseurl' => $source['url'],
            ]) . "\n";
        }

        return $lines;
    }
}
