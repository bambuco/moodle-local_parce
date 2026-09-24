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
     * Hard cap on planner intents accepted in one turn (anti-abuse).
     */
    public const MAX_PLAN_INTENTS = 8;

    /**
     * Default and bounds for max_require_ia_intents.
     */
    public const DEFAULT_MAX_REQUIRE_IA = 2;
    public const MIN_MAX_REQUIRE_IA = 1;
    public const MAX_MAX_REQUIRE_IA = 5;

    /**
     * Intent types accepted from the planner.
     */
    private const INTENT_TYPES = [
        'base', 'content', 'course', 'dates', 'grades', 'greeting', 'help', 'progress', 'resource',
    ];

    /**
     * Error codes that mean retrieval found nothing for an intent.
     */
    private const NOT_FOUND_ERRORS = [
        'intent_content_notfound',
        'intent_course_notfound',
        'intent_dates_notfound',
        'intent_grades_notfound',
        'intent_progress_notfound',
        'intent_resource_notfound',
    ];

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
     * @var string Standalone form of the last planned question for later conversation prompts.
     */
    private static string $lastresolvedquestion = '';

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
     * Get the standalone form of the last planned question.
     *
     * @return string Resolved question text, or empty when planning did not complete.
     */
    public static function get_last_resolved_question(): string {
        return self::$lastresolvedquestion;
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
     * Read and clamp the max require_ia intents setting.
     *
     * @return int Value between MIN_MAX_REQUIRE_IA and MAX_MAX_REQUIRE_IA.
     */
    public static function get_max_require_ia_intents(): int {
        $value = get_config('local_parce', 'max_require_ia_intents');
        if ($value === false || $value === null || $value === '') {
            return self::DEFAULT_MAX_REQUIRE_IA;
        }
        $value = (int) $value;
        if ($value < self::MIN_MAX_REQUIRE_IA || $value > self::MAX_MAX_REQUIRE_IA) {
            return self::DEFAULT_MAX_REQUIRE_IA;
        }
        return $value;
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
        self::$lastresolvedquestion = '';
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

            $previous = self::get_conversation_context($USER->id, $chatid);
            $planprompt = get_config('local_parce', 'question_plan_prompt');
            if (empty($planprompt)) {
                $planprompt = get_string('default_question_plan_prompt', 'local_parce');
            }
            $answerprompt = get_config('local_parce', 'answer_question_prompt');
            if (empty($answerprompt)) {
                $answerprompt = get_string('default_answer_question_prompt', 'local_parce');
            }
            $coursecard = controller::build_course_card($context, $USER->id);
            $resourcetypes = intent\resource::get_module_type_catalogue($context);
            $resourcetypes = empty($resourcetypes) ? '' : json_encode($resourcetypes, JSON_UNESCAPED_SLASHES);

            $planquestion = $question;
            $allowretry = $previous !== [];
            $retried = false;

            while (true) {
                $planned = self::plan_question(
                    $gateway,
                    $context,
                    $USER->id,
                    $chatid,
                    $conversationkey,
                    $requestid,
                    $planprompt,
                    $planquestion,
                    $previous,
                    $resourcetypes,
                    $coursecard,
                    $cacheversion
                );
                if (is_string($planned)) {
                    return $planned;
                }

                $intents = $planned['intents'];
                $resolvedquestion = $planned['resolvedquestion'];
                self::$lastresolvedquestion = $resolvedquestion;

                $selected = self::select_intents($intents, $context);
                $collection = self::collect_intent_bundles($selected['admitted'], $context);
                $deferred = array_merge($selected['deferred'], $collection['deferred']);
                $bundles = $collection['bundles'];

                if ($bundles === []) {
                    if ($deferred !== []) {
                        return self::success_response(
                            $question,
                            get_string('msg_no_content', 'local_parce') . "\n\n" . self::format_deferred_footer($deferred),
                            false
                        );
                    }
                    return self::success_response($question, get_string('msg_no_content', 'local_parce'), false);
                }

                // Single-intent path keeps the previous direct-return behaviour.
                if (count($intents) === 1 && count($bundles) === 1 && $deferred === []) {
                    $single = self::process_single_intent_response(
                        $gateway,
                        $context,
                        $USER->id,
                        $chatid,
                        $conversationkey,
                        $requestid,
                        $answerprompt,
                        $resolvedquestion,
                        $previous,
                        $coursecard,
                        $cacheversion,
                        $bundles[0],
                        $allowretry,
                        $retried,
                        $planquestion,
                        $question
                    );
                    if (is_array($single) && ($single['retry'] ?? false)) {
                        $planquestion = $single['planquestion'];
                        $retried = true;
                        continue;
                    }
                    return $single;
                }

                $response = self::process_multi_intent_response(
                    $gateway,
                    $context,
                    $USER->id,
                    $chatid,
                    $conversationkey,
                    $requestid,
                    $answerprompt,
                    $question,
                    $resolvedquestion,
                    $previous,
                    $coursecard,
                    $cacheversion,
                    $bundles,
                    $deferred,
                    $allowretry,
                    $retried,
                    $planquestion
                );
                if (is_array($response) && ($response['retry'] ?? false)) {
                    $planquestion = $response['planquestion'];
                    $retried = true;
                    continue;
                }
                return $response;
            }
        } catch (\core\exception\coding_exception $e) {
            return self::failure_response('error_ai_unavailable', 'ai_unavailable', true, $e->getMessage());
        } catch (\Throwable $e) {
            return self::failure_response('error_processing_question', 'processing_error', true, $e->getMessage());
        }
    }

    /**
     * Handle a single planned intent using the legacy return rules.
     *
     * @param array $bundle Collected bundle for the intent
     * @return string|array Display string, or retry directive
     */
    private static function process_single_intent_response(
        ai_gateway $gateway,
        object $context,
        int $userid,
        int $chatid,
        string $conversationkey,
        string $requestid,
        string $answerprompt,
        string $resolvedquestion,
        array $previous,
        string $coursecard,
        int $cacheversion,
        array $bundle,
        bool $allowretry,
        bool $retried,
        string $planquestion,
        string $question
    ): string|array {
        $intentname = $bundle['type'];
        $intentparams = $bundle['params'];
        $content = $bundle['content'];

        if ($bundle['status'] === 'not_found') {
            return self::success_response($question, $bundle['message'] !== '' ? $bundle['message'] : get_string('msg_no_content', 'local_parce'), false);
        }

        if (!$bundle['require_ia']) {
            return self::success_response($question, $content);
        }

        if ($intentname === 'content' && intent\content::are_link_only_results($content)) {
            $resources = intent\content::format_search_results($content, 'resource_results');
            if ($resources !== '') {
                return self::success_response($question, $resources);
            }
        }

        if ($content === '') {
            $allowopenanswer = get_config('local_parce', 'allowopenanswer');
            if (!$allowopenanswer) {
                return self::success_response($question, get_string('msg_no_content', 'local_parce'), false);
            }
            $content = get_config('local_parce', 'openanswer_prompt');
            if (empty($content)) {
                $content = get_string('default_openanswer_prompt', 'local_parce');
            }
        }

        $answered = self::answer_question(
            $gateway,
            $context,
            $userid,
            $chatid,
            $conversationkey,
            $requestid,
            $answerprompt,
            $resolvedquestion,
            $previous,
            $content,
            $coursecard,
            $cacheversion,
            $intentname,
            $intentparams
        );
        if (is_string($answered)) {
            return $answered;
        }

        if (($answered['status'] ?? '') === 'not_found') {
            if ($allowretry && !$retried) {
                return ['retry' => true, 'planquestion' => $resolvedquestion];
            }
            $suggestions = intent\content::format_search_results($content, 'content_suggestions');
            if ($suggestions !== '') {
                return self::success_response($question, $suggestions);
            }
            return self::success_response($question, get_string('answer_notfound', 'local_parce'), false);
        }

        $generatedcontent = $answered['generatedcontent'];
        if ($intentname !== 'course') {
            $generatedcontent .= self::build_course_references($content, $context, $generatedcontent);
        }

        return self::success_response($question, $generatedcontent);
    }

    /**
     * Collect data for several intents and ask the answer model once.
     *
     * @param array $bundles Retrieved intent bundles
     * @param array $deferred Deferred intent descriptors
     * @return string|array Display string, or retry directive
     */
    private static function process_multi_intent_response(
        ai_gateway $gateway,
        object $context,
        int $userid,
        int $chatid,
        string $conversationkey,
        string $requestid,
        string $answerprompt,
        string $question,
        string $resolvedquestion,
        array $previous,
        string $coursecard,
        int $cacheversion,
        array $bundles,
        array $deferred,
        bool $allowretry,
        bool $retried,
        string $planquestion
    ): string|array {
        $needsanswer = count($bundles) > 1;
        foreach ($bundles as $bundle) {
            if (!empty($bundle['require_ia'])) {
                $needsanswer = true;
                break;
            }
        }

        // Multi light-only: still one answer so the model unifies greeting + links + course facts.
        if (!$needsanswer && count($bundles) === 1) {
            $text = $bundles[0]['content'];
            if ($deferred !== []) {
                $text .= "\n\n" . self::format_deferred_footer($deferred);
            }
            return self::success_response($question, $text);
        }

        $content = self::format_intent_bundles($bundles);
        if ($content === '') {
            $allowopenanswer = get_config('local_parce', 'allowopenanswer');
            if (!$allowopenanswer) {
                $text = get_string('msg_no_content', 'local_parce');
                if ($deferred !== []) {
                    $text .= "\n\n" . self::format_deferred_footer($deferred);
                }
                return self::success_response($question, $text, false);
            }
            $content = get_config('local_parce', 'openanswer_prompt');
            if (empty($content)) {
                $content = get_string('default_openanswer_prompt', 'local_parce');
            }
        }

        $traceparams = [
            'intents' => array_map(static function(array $bundle): array {
                return [
                    'type' => $bundle['type'],
                    'params' => $bundle['params'],
                    'resolvedquestion' => $bundle['resolvedquestion'],
                    'status' => $bundle['status'],
                    'require_ia' => $bundle['require_ia'],
                ];
            }, $bundles),
            'deferred' => array_map(static function(array $item): array {
                return [
                    'type' => $item['type'],
                    'resolvedquestion' => $item['resolvedquestion'],
                ];
            }, $deferred),
        ];

        $answered = self::answer_question(
            $gateway,
            $context,
            $userid,
            $chatid,
            $conversationkey,
            $requestid,
            $answerprompt,
            $question,
            $previous,
            $content,
            $coursecard,
            $cacheversion,
            count($bundles) === 1 ? $bundles[0]['type'] : 'multi',
            $traceparams
        );
        if (is_string($answered)) {
            return $answered;
        }

        if (($answered['status'] ?? '') === 'not_found') {
            if ($allowretry && !$retried) {
                return ['retry' => true, 'planquestion' => $resolvedquestion];
            }
            $text = get_string('answer_notfound', 'local_parce');
            if ($deferred !== []) {
                $text .= "\n\n" . self::format_deferred_footer($deferred);
            }
            return self::success_response($question, $text, false);
        }

        $generatedcontent = $answered['generatedcontent'];
        if ($deferred !== []) {
            $generatedcontent .= "\n\n" . self::format_deferred_footer($deferred);
        }

        return self::success_response($question, $generatedcontent);
    }

    /**
     * Split planned intents into admitted and deferred by require_ia quota.
     *
     * @param array $intents Normalised intent list
     * @param object $context Moodle context
     * @return array{admitted: array, deferred: array}
     */
    private static function select_intents(array $intents, object $context): array {
        $maxheavy = self::get_max_require_ia_intents();
        $admitted = [];
        $deferred = [];
        $heavycount = 0;

        foreach ($intents as $index => $intent) {
            if (count($admitted) >= self::MAX_PLAN_INTENTS) {
                $deferred[] = $intent;
                continue;
            }

            $intentclass = '\local_parce\local\intent\\' . $intent['type'];
            if (!class_exists($intentclass)) {
                $deferred[] = $intent;
                continue;
            }

            $probe = new $intentclass($context, null, $intent['params']);
            $requireia = $probe->require_ia();
            $intent['require_ia'] = $requireia;
            $intent['index'] = $index;

            if ($requireia) {
                if ($heavycount < $maxheavy) {
                    $admitted[] = $intent;
                    $heavycount++;
                } else {
                    $deferred[] = $intent;
                }
            } else {
                $admitted[] = $intent;
            }
        }

        return ['admitted' => $admitted, 'deferred' => $deferred];
    }

    /**
     * Retrieve content for admitted intents within the retrieved-token budget.
     *
     * @param array $admitted Admitted intents
     * @param object $context Moodle context
     * @return array{bundles: array, deferred: array}
     */
    private static function collect_intent_bundles(array $admitted, object $context): array {
        $bundles = [];
        $deferred = [];
        $usedtokens = 0;
        $budget = controller::MAX_RETRIEVED_TOKENS;

        foreach ($admitted as $index => $intent) {
            $intentclass = '\local_parce\local\intent\\' . $intent['type'];
            $intentobj = new $intentclass($context, null, $intent['params']);
            $status = 'ok';
            $content = '';
            $message = '';

            try {
                $content = $intentobj->get_content();
                if ($content === '') {
                    $status = 'empty';
                }
            } catch (\moodle_exception $e) {
                if (in_array($e->errorcode, self::NOT_FOUND_ERRORS, true)) {
                    $status = 'not_found';
                    $message = $e->getMessage();
                    $content = $message;
                } else {
                    throw $e;
                }
            }

            $candidate = [
                'type' => $intent['type'],
                'params' => $intent['params'],
                'resolvedquestion' => $intent['resolvedquestion'],
                'require_ia' => !empty($intent['require_ia']),
                'status' => $status,
                'content' => $content,
                'message' => $message,
            ];
            $tokens = controller::estimate_payload_tokens(self::format_intent_bundle($candidate));
            if ($bundles !== [] && ($usedtokens + $tokens) > $budget) {
                for ($i = $index; $i < count($admitted); $i++) {
                    $deferred[] = $admitted[$i];
                }
                break;
            }

            $usedtokens += $tokens;
            $bundles[] = $candidate;
        }

        return ['bundles' => $bundles, 'deferred' => $deferred];
    }

    /**
     * Build CONTENT blocks for the answer model.
     *
     * @param array $bundles Intent bundles
     * @return string Delimited intent content
     */
    private static function format_intent_bundles(array $bundles): string {
        $parts = [];
        foreach ($bundles as $bundle) {
            $parts[] = self::format_intent_bundle($bundle);
        }
        return implode("\n", $parts);
    }

    /**
     * Format one intent bundle for the answer payload.
     *
     * @param array $bundle Intent bundle
     * @return string
     */
    private static function format_intent_bundle(array $bundle): string {
        $type = preg_replace('/[^a-z_]/', '', (string) $bundle['type']);
        $status = preg_replace('/[^a-z_]/', '', (string) $bundle['status']);
        $resolved = str_replace(['"', '<', '>'], ["'", '', ''], (string) $bundle['resolvedquestion']);
        return '<INTENT_START type="' . $type . '" status="' . $status . '" resolved="' . $resolved . '">'
            . ($bundle['content'] ?? '')
            . '<INTENT_END>';
    }

    /**
     * Build the deferred-intents footer (#9).
     *
     * @param array $deferred Deferred intents
     * @return string
     */
    private static function format_deferred_footer(array $deferred): string {
        $labels = [];
        foreach ($deferred as $item) {
            $label = trim((string) ($item['resolvedquestion'] ?? ''));
            if ($label === '') {
                $label = (string) ($item['type'] ?? '');
            }
            if ($label !== '' && !in_array($label, $labels, true)) {
                $labels[] = $label;
            }
        }
        if ($labels === []) {
            return '';
        }
        return get_string('deferred_intents_footer', 'local_parce', implode('; ', $labels));
    }

    /**
     * Plan the intent for one question attempt.
     *
     * @param ai_gateway $gateway AI gateway
     * @param object $context Moodle context
     * @param int $userid User ID
     * @param int $chatid Chat context ID
     * @param string $conversationkey Conversation key
     * @param string $requestid Request ID
     * @param string $planprompt System instruction for planning
     * @param string $planquestion Question text sent to the planner
     * @param array $previous Prior conversation messages
     * @param string $resourcetypes Resource type catalogue JSON
     * @param string $coursecard Course identity card JSON
     * @param int $cacheversion Active cache version
     * @return array|string Planned intent data, or a failure display string
     */
    private static function plan_question(
        ai_gateway $gateway,
        object $context,
        int $userid,
        int $chatid,
        string $conversationkey,
        string $requestid,
        string $planprompt,
        string $planquestion,
        array $previous,
        string $resourcetypes,
        string $coursecard,
        int $cacheversion
    ): array|string {
        $hackquestion = controller::build_ai_payload($planquestion, $previous, '', $resourcetypes, $coursecard);
        $action = new question_plan(
            contextid: $context->id,
            userid: $userid,
            prompttext: $hackquestion
        );

        $generation = self::traced_generate(
            $gateway,
            $action,
            $userid,
            $context->id,
            $chatid,
            $conversationkey,
            $requestid,
            'question_plan',
            $planprompt,
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
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                    return 'invalid_json';
                }
                $normalised = self::normalise_planned_intents($decoded, '');
                return $normalised === null ? 'invalid_intent' : 'success';
            }
        );
        if ($generation === null) {
            return self::failure_response('error_ai_unavailable', 'ai_unavailable', true);
        }
        $response = $generation['response'];
        if (!controller::is_active_cache_version($cacheversion)) {
            return self::failure_response('error_processing_question', 'request_cancelled', true);
        }

        if (!$response->get_success()) {
            return self::provider_failure_response($response, 'planning_failed');
        }
        $responsedata = $response->get_response_data();
        if (empty($responsedata['generatedcontent'])) {
            return self::failure_response('error_no_content', 'planning_empty', true);
        }

        $decoded = json_decode($responsedata['generatedcontent'], true);
        $normalised = self::normalise_planned_intents(is_array($decoded) ? $decoded : [], $planquestion);
        if ($normalised === null) {
            return self::failure_response('error_processing_question', 'invalid_intent', true);
        }

        $storedparams = [
            'intents' => $normalised['intents'],
            'resolvedquestion' => $normalised['resolvedquestion'],
        ];
        $traceintent = count($normalised['intents']) === 1 ? $normalised['intents'][0]['type'] : 'multi';
        foreach (self::$lastcallids as $actionid) {
            controller::update_ai_action($actionid, $traceintent, $storedparams);
        }

        return $normalised;
    }

    /**
     * Normalise planner JSON to an intents list (supports legacy single type).
     *
     * @param array $decoded Decoded planner JSON
     * @param string $fallbackquestion Fallback when resolvedquestion is missing
     * @return array{intents: array, resolvedquestion: string}|null
     */
    private static function normalise_planned_intents(array $decoded, string $fallbackquestion): ?array {
        $rawintents = [];
        if (isset($decoded['intents']) && is_array($decoded['intents'])) {
            $rawintents = $decoded['intents'];
        } else if (!empty($decoded['type'])) {
            $rawintents = [$decoded];
        } else {
            return null;
        }

        $intents = [];
        foreach ($rawintents as $item) {
            if (!is_array($item) || empty($item['type']) || !in_array($item['type'], self::INTENT_TYPES, true)) {
                return null;
            }
            $params = $item['params'] ?? [];
            if (!is_array($params)) {
                $params = [$params];
            }
            $resolved = trim((string) ($item['resolvedquestion'] ?? ''));
            if ($resolved === '') {
                $resolved = $fallbackquestion;
            }
            $intents[] = [
                'type' => $item['type'],
                'params' => $params,
                'resolvedquestion' => $resolved,
            ];
            if (count($intents) >= self::MAX_PLAN_INTENTS) {
                break;
            }
        }

        if ($intents === []) {
            return null;
        }

        $resolvedparts = array_values(array_filter(
            array_map(static fn(array $intent): string => trim($intent['resolvedquestion']), $intents),
            static fn(string $part): bool => $part !== ''
        ));
        $resolvedquestion = $resolvedparts === [] ? $fallbackquestion : implode(' | ', $resolvedparts);

        return [
            'intents' => $intents,
            'resolvedquestion' => $resolvedquestion,
        ];
    }

    /**
     * Ask the answer model using the resolved standalone question.
     *
     * @param ai_gateway $gateway AI gateway
     * @param object $context Moodle context
     * @param int $userid User ID
     * @param int $chatid Chat context ID
     * @param string $conversationkey Conversation key
     * @param string $requestid Request ID
     * @param string $answerprompt System instruction for answering
     * @param string $resolvedquestion Standalone question for the answerer
     * @param array $previous Prior conversation messages
     * @param string $content Retrieved content
     * @param string $coursecard Course identity card JSON
     * @param int $cacheversion Active cache version
     * @param string $intentname Detected intent type
     * @param array $intentparams Intent parameters without resolvedquestion
     * @return array|string Answer payload, not_found status, or a failure display string
     */
    private static function answer_question(
        ai_gateway $gateway,
        object $context,
        int $userid,
        int $chatid,
        string $conversationkey,
        string $requestid,
        string $answerprompt,
        string $resolvedquestion,
        array $previous,
        string $content,
        string $coursecard,
        int $cacheversion,
        string $intentname,
        array $intentparams
    ): array|string {
        $hackquestion = controller::build_ai_payload($resolvedquestion, $previous, $content, '', $coursecard);
        $action = new question_plan(
            contextid: $context->id,
            userid: $userid,
            prompttext: $hackquestion
        );

        $generation = self::traced_generate(
            $gateway,
            $action,
            $userid,
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

        $storedparams = $intentparams;
        $storedparams['resolvedquestion'] = $resolvedquestion;
        foreach (self::$lastcallids as $actionid) {
            controller::update_ai_action($actionid, $intentname, $storedparams);
        }

        if (!$response->get_success()) {
            return self::provider_failure_response($response, 'response_failed');
        }
        $responsedata = $response->get_response_data();
        if (empty($responsedata['generatedcontent'])) {
            return self::failure_response('error_no_content', 'response_empty', true);
        }

        $generatedcontent = $responsedata['generatedcontent'];
        // NOT_FOUND is an internal provider sentinel and must never be displayed or decorated with references.
        if (trim($generatedcontent) === 'NOT_FOUND') {
            return ['status' => 'not_found'];
        }

        return [
            'status' => 'success',
            'generatedcontent' => $generatedcontent,
        ];
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
