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
 * Tests for the local_parce question handler.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_parce\tests\local;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../fixtures/test_provider.php');
require_once(__DIR__ . '/../fixtures/test_ai_gateway.php');

/**
 * Tests for question handler provider resolution.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_parce\local\question_handler::class)]
final class question_handler_test extends \advanced_testcase {
    /**
     * Missing providers produce a retryable operational error without a provider call.
     */
    public function test_missing_provider_result(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $gateway = new test_ai_gateway([], null);

        \local_parce\local\question_handler::process('Question', \context_system::instance(), $gateway);

        $this->assertSame([
            'status' => 'error',
            'successful' => false,
            'retryable' => true,
            'errorcode' => 'ai_unavailable',
        ], \local_parce\local\question_handler::get_last_result());
        $this->assertSame(0, $gateway->get_generate_calls());
        $trace = $DB->get_record('local_parce_ai_actions', [], '*', MUST_EXIST);
        $this->assertSame('completed', $trace->status);
        $this->assertSame('no_provider', $trace->outcome);
        $this->assertSame(0, (int) $trace->attemptordinal);
        $this->assertNotEmpty($trace->timecompleted);
    }

    /**
     * When no BBCO provider is registered, the resolver returns null.
     */
    public function test_resolve_ai_provider_returns_null_when_missing(): void {
        $this->resetAfterTest();

        $provider = \local_parce\local\question_handler::resolve_ai_provider();

        $this->assertNull($provider);
    }

    /**
     * Provider details are never exposed by the public answer.
     */
    public function test_provider_error_details_are_never_exposed(): void {
        global $CFG;

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $provider = new test_provider(true, 'fake', '[]', id: 1);
        $technical = 'upstream secret failure';

        $CFG->debug = DEBUG_NORMAL;
        $response = new \local_parce\aiactions\responses\response_question_plan(
            false,
            503,
            'provider_error',
            $technical
        );
        $gateway = new test_ai_gateway([$response], $provider);
        $result = \local_parce\local\question_handler::process(
            'Question',
            \context_system::instance(),
            $gateway
        );
        $this->assertStringNotContainsString($technical, $result);

        $CFG->debug = DEBUG_DEVELOPER;
        $response = new \local_parce\aiactions\responses\response_question_plan(
            false,
            503,
            'provider_error',
            $technical
        );
        $gateway = new test_ai_gateway([$response], $provider);
        $result = \local_parce\local\question_handler::process(
            'Question',
            \context_system::instance(),
            $gateway
        );
        $this->assertStringNotContainsString($technical, $result);
    }

    /**
     * Resolver exceptions are distinct from a valid no-provider result and still close the trace.
     */
    public function test_provider_resolution_exception_is_traced(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $gateway = new class ([], null) extends test_ai_gateway {
            /**
             * Simulate an infrastructure failure while resolving BBCO.
             *
             * @return \core_ai\provider|null
             */
            public function resolve_provider(): ?\core_ai\provider {
                throw new \RuntimeException('private resolver failure');
            }
        };

        $answer = \local_parce\local\question_handler::process('Question', \context_system::instance(), $gateway);

        $trace = $DB->get_record('local_parce_ai_actions', [], '*', MUST_EXIST);
        $this->assertSame('completed', $trace->status);
        $this->assertSame('exception', $trace->outcome);
        $this->assertSame(0, (int) $trace->attemptordinal);
        $this->assertSame('private resolver failure', $trace->errormessage);
        $this->assertStringNotContainsString('private resolver failure', $answer);
    }

    /**
     * Provider failures retain terminal/retryable and rate-limit semantics.
     *
     * @param int $providercode Provider HTTP-style code
     * @param string $status Expected discriminator
     * @param bool $retryable Expected retry flag
     * @param string $errorcode Expected stable code
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_failure_provider')]
    public function test_planning_provider_failure_contract(
        int $providercode,
        string $status,
        bool $retryable,
        string $errorcode
    ): void {
        global $DB;

        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $provider = new test_provider(true, 'fake', '[]', id: 1);
        $response = new \local_parce\aiactions\responses\response_question_plan(
            false,
            $providercode,
            'provider_error',
            'technical detail'
        );
        $gateway = new test_ai_gateway([$response], $provider);

        \local_parce\local\question_handler::process('Question', \context_system::instance(), $gateway);

        $this->assertSame([
            'status' => $status,
            'successful' => false,
            'retryable' => $retryable,
            'errorcode' => $errorcode,
        ], \local_parce\local\question_handler::get_last_result());
        $this->assertSame(1, $gateway->get_generate_calls());
        $trace = $DB->get_record('local_parce_ai_actions', [], '*', MUST_EXIST);
        $this->assertSame('completed', $trace->status);
        $this->assertSame($providercode === 429 ? 'rate_limited' : 'provider_error', $trace->outcome);
        $this->assertSame(1, (int) $trace->attemptordinal);
    }

    /**
     * Provider failure cases.
     *
     * @return array
     */
    public static function provider_failure_provider(): array {
        return [
            'rate limit is terminal for the operation' => [429, 'rate_limited', true, 'rate_limited'],
            'client failure is not retryable' => [400, 'error', false, 'planning_failed'],
            'server failure is retryable' => [503, 'error', true, 'planning_failed'],
        ];
    }

    /**
     * Empty and malformed planning responses are structured recoverable errors.
     *
     * @param string|null $content Generated planning content
     * @param string $errorcode Expected stable code
     * @param string $outcome Expected trace outcome
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalid_planning_response_provider')]
    public function test_invalid_planning_response_contract(
        ?string $content,
        string $errorcode,
        string $outcome
    ): void {
        global $DB;

        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $provider = new test_provider(true, 'fake', '[]', id: 1);
        $response = new \local_parce\aiactions\responses\response_question_plan(true);
        $response->set_response_data(['generatedcontent' => $content, 'model' => 'fake-model']);
        $gateway = new test_ai_gateway([$response], $provider);

        \local_parce\local\question_handler::process('Question', \context_system::instance(), $gateway);
        $result = \local_parce\local\question_handler::get_last_result();

        $this->assertSame('error', $result['status']);
        $this->assertTrue($result['retryable']);
        $this->assertSame($errorcode, $result['errorcode']);
        $trace = $DB->get_record('local_parce_ai_actions', [], '*', MUST_EXIST);
        $this->assertSame('completed', $trace->status);
        $this->assertSame($outcome, $trace->outcome);
    }

    /**
     * Invalid planning response cases.
     *
     * @return array
     */
    public static function invalid_planning_response_provider(): array {
        return [
            'empty response' => [null, 'planning_empty', 'empty_response'],
            'invalid JSON' => ['not-json', 'invalid_intent', 'invalid_json'],
            'unknown intent' => [json_encode(['type' => 'unknown']), 'invalid_intent', 'invalid_intent'],
        ];
    }

    /**
     * Gateway exceptions, including timeouts, are safe retryable errors.
     */
    public function test_gateway_timeout_contract(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $CFG->debug = DEBUG_NORMAL;
        $provider = new test_provider(true, 'fake', '[]', id: 1);
        $gateway = new test_ai_gateway([new \RuntimeException('connection timed out: secret host')], $provider);

        $answer = \local_parce\local\question_handler::process('Question', \context_system::instance(), $gateway);
        $result = \local_parce\local\question_handler::get_last_result();

        $this->assertSame('processing_error', $result['errorcode']);
        $this->assertTrue($result['retryable']);
        $this->assertStringNotContainsString('secret host', $answer);
        $this->assertSame(1, $gateway->get_generate_calls());
        $trace = $DB->get_record('local_parce_ai_actions', [], '*', MUST_EXIST);
        $this->assertSame('completed', $trace->status);
        $this->assertSame('timeout', $trace->outcome);
        $this->assertSame(1, (int) $trace->attemptordinal);
        $this->assertGreaterThan(0, (int) $trace->durationms);
    }

    /**
     * Controlled provider latency is measured in milliseconds.
     */
    public function test_trace_records_positive_controlled_delay(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $provider = new test_provider(true, 'fake', '[]', id: 1);
        $response = new \local_parce\aiactions\responses\response_question_plan(true);
        $response->set_response_data([
            'generatedcontent' => json_encode(['type' => 'base', 'params' => []]),
            'model' => 'fake-model',
        ]);
        $gateway = new test_ai_gateway([$response], $provider);
        $gateway->delay = 20_000;

        \local_parce\local\question_handler::process('Question', \context_system::instance(), $gateway);

        $trace = $DB->get_record('local_parce_ai_actions', [], '*', MUST_EXIST);
        $this->assertGreaterThanOrEqual(20, (int) $trace->durationms);
        $this->assertSame('success', $trace->outcome);
    }

    /**
     * All fallback attempts remain ordered and correlated in the authoritative table.
     */
    public function test_fallback_attempts_are_correlated_and_ordered(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $provider = new test_provider(true, 'fake', '[]', id: 1);
        $model = str_repeat('long-model-', 12);
        $response = new \local_parce\aiactions\responses\response_question_plan(true);
        $response->set_response_data([
            'generatedcontent' => json_encode(['type' => 'base', 'params' => []]),
            'model' => $model,
        ]);
        $gateway = new test_ai_gateway([[
            'response' => $response,
            'attempts' => [
                [
                    'attemptordinal' => 1,
                    'providercomponent' => 'aiprovider_first',
                    'providerinstanceid' => 11,
                    'providername' => 'first',
                    'success' => false,
                    'errorcode' => 503,
                    'durationms' => 5,
                ],
                [
                    'attemptordinal' => 2,
                    'providercomponent' => 'aiprovider_second',
                    'providerinstanceid' => 12,
                    'providername' => 'second',
                    'success' => true,
                    'model' => $model,
                    'prompttokens' => 10,
                    'completiontokens' => 4,
                    'durationms' => 7,
                ],
            ],
        ]], $provider);

        \local_parce\local\question_handler::process('Question', \context_system::instance(), $gateway);

        $traces = array_values($DB->get_records('local_parce_ai_actions', [], 'attemptordinal'));
        $this->assertCount(2, $traces);
        $this->assertSame([1, 2], array_map(fn($trace) => (int) $trace->attemptordinal, $traces));
        $this->assertSame($traces[0]->requestid, $traces[1]->requestid);
        $this->assertSame($traces[0]->callid, $traces[1]->callid);
        $this->assertSame('provider_error', $traces[0]->outcome);
        $this->assertSame('success', $traces[1]->outcome);
        $this->assertSame($model, $traces[1]->model);
        $this->assertSame(10, (int) $traces[1]->prompttokens);
    }

    /**
     * A failed final generation is distinguished and never marked successful.
     */
    public function test_final_response_failure_contract(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $provider = new test_provider(true, 'fake', '[]', id: 1);
        $plan = new \local_parce\aiactions\responses\response_question_plan(true);
        $plan->set_response_data([
            'generatedcontent' => json_encode(['type' => 'content', 'params' => []]),
            'model' => 'fake-model',
        ]);
        $failure = new \local_parce\aiactions\responses\response_question_plan(
            false,
            503,
            'provider_error',
            'upstream unavailable'
        );
        $gateway = new test_ai_gateway([$plan, $failure], $provider);

        \local_parce\local\question_handler::process('Question', \context_system::instance(), $gateway);

        $this->assertSame('response_failed', \local_parce\local\question_handler::get_last_result()['errorcode']);
        $this->assertFalse(\local_parce\local\question_handler::was_last_successful());
        $this->assertSame(2, $gateway->get_generate_calls());
    }

    /**
     * Resource intents finish after Moodle Search and never request an AI-generated answer.
     */
    public function test_resource_intent_does_not_make_answer_call(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $provider = new test_provider(true, 'fake', '[]', id: 1);
        $plan = new \local_parce\aiactions\responses\response_question_plan(true);
        $plan->set_response_data([
            'generatedcontent' => json_encode(['type' => 'resource', 'params' => []]),
            'model' => 'fake-model',
        ]);
        $gateway = new test_ai_gateway([$plan], $provider);

        $answer = \local_parce\local\question_handler::process(
            'Find a course',
            \context_system::instance(),
            $gateway
        );

        $this->assertSame(get_string('intent_resource_notfound', 'local_parce'), $answer);
        $this->assertSame(1, $gateway->get_generate_calls());
        $this->assertTrue(\local_parce\local\question_handler::get_last_result()['successful']);
        $this->assertFalse(\local_parce\local\question_handler::was_last_successful());
    }

    /**
     * Grade questions use the user's visible grade report as answer context.
     */
    public function test_grades_intent_answers_from_visible_grade(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Seguridad digital']);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assignment = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'name' => 'Quiz de seguridad',
        ]);
        grade_update('mod/assign', $course->id, 'mod', 'assign', $assignment->id, 0, [
            'userid' => $student->id,
            'rawgrade' => 85,
        ]);
        $this->setUser($student);

        $provider = new test_provider(true, 'fake', '[]', id: 1);
        $plan = new \local_parce\aiactions\responses\response_question_plan(true);
        $plan->set_response_data([
            'generatedcontent' => json_encode([
                'type' => 'grades',
                'params' => ['grades' => ['Quiz de seguridad']],
            ]),
            'model' => 'fake-model',
        ]);
        $response = new \local_parce\aiactions\responses\response_question_plan(true);
        $response->set_response_data([
            'generatedcontent' => 'Obtuviste 85 de 100.',
            'model' => 'fake-model',
        ]);
        $gateway = new test_ai_gateway([$plan, $response], $provider);

        $answer = \local_parce\local\question_handler::process(
            '¿Qué calificación obtuve en el Quiz de seguridad?',
            \context_course::instance($course->id),
            $gateway
        );

        $this->assertSame('Obtuviste 85 de 100.', $answer);
        $this->assertSame(2, $gateway->get_generate_calls());
        $answertrace = $DB->get_record(
            'local_parce_ai_actions',
            ['actiontype' => 'answer_question'],
            '*',
            MUST_EXIST
        );
        $this->assertStringContainsString('Quiz de seguridad', $answertrace->prompttext);
        $this->assertStringContainsString('85.00', $answertrace->prompttext);
        $this->assertSame('grades', $answertrace->intent);
    }

    /**
     * Progress questions include only visible activities matching the requested completion state.
     */
    public function test_progress_intent_answers_from_pending_activities(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        $CFG->enablecompletion = true;
        $course = $this->getDataGenerator()->create_course([
            'fullname' => 'Ciudadanía digital',
            'enablecompletion' => true,
        ]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $completed = $this->getDataGenerator()->create_module(
            'page',
            ['course' => $course->id, 'name' => 'Introducción'],
            ['completion' => COMPLETION_TRACKING_MANUAL]
        );
        $this->getDataGenerator()->create_module(
            'page',
            ['course' => $course->id, 'name' => 'Actividad de privacidad'],
            ['completion' => COMPLETION_TRACKING_MANUAL]
        );
        $this->setUser($student);
        $completion = new \completion_info($course);
        $cm = get_fast_modinfo($course, $student->id)->get_cm($completed->cmid);
        $completion->update_state($cm, COMPLETION_COMPLETE, $student->id);

        $provider = new test_provider(true, 'fake', '[]', id: 1);
        $plan = new \local_parce\aiactions\responses\response_question_plan(true);
        $plan->set_response_data([
            'generatedcontent' => json_encode([
                'type' => 'progress',
                'params' => ['progress' => [], 'status' => 'incomplete'],
            ]),
            'model' => 'fake-model',
        ]);
        $response = new \local_parce\aiactions\responses\response_question_plan(true);
        $response->set_response_data([
            'generatedcontent' => 'Tienes pendiente la Actividad de privacidad.',
            'model' => 'fake-model',
        ]);
        $gateway = new test_ai_gateway([$plan, $response], $provider);

        $answer = \local_parce\local\question_handler::process(
            '¿Qué actividades tengo pendientes en este curso?',
            \context_course::instance($course->id),
            $gateway
        );

        $this->assertSame('Tienes pendiente la Actividad de privacidad.', $answer);
        $this->assertSame(2, $gateway->get_generate_calls());
        $answertrace = $DB->get_record(
            'local_parce_ai_actions',
            ['actiontype' => 'answer_question'],
            '*',
            MUST_EXIST
        );
        $this->assertStringContainsString('Actividad de privacidad', $answertrace->prompttext);
        $this->assertStringNotContainsString('Introducción', $answertrace->prompttext);
        $this->assertStringContainsString('incomplete', $answertrace->prompttext);
        $this->assertSame('progress', $answertrace->intent);
    }

    /**
     * Progress is not fabricated for users whose completion is not tracked.
     */
    public function test_progress_intent_rejects_untracked_user_without_open_answer(): void {
        global $CFG;

        $this->resetAfterTest();
        $CFG->enablecompletion = true;
        set_config('allowopenanswer', 1, 'local_parce');
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => true]);
        $this->getDataGenerator()->create_module(
            'page',
            ['course' => $course->id, 'name' => 'Tracked page'],
            ['completion' => COMPLETION_TRACKING_MANUAL]
        );
        $this->setUser($this->getDataGenerator()->create_user());

        $provider = new test_provider(true, 'fake', '[]', id: 1);
        $plan = new \local_parce\aiactions\responses\response_question_plan(true);
        $plan->set_response_data([
            'generatedcontent' => json_encode([
                'type' => 'progress',
                'params' => ['progress' => []],
            ]),
            'model' => 'fake-model',
        ]);
        $gateway = new test_ai_gateway([$plan], $provider);

        $answer = \local_parce\local\question_handler::process(
            'What is my progress?',
            \context_course::instance($course->id),
            $gateway
        );

        $this->assertSame(get_string('intent_progress_notfound', 'local_parce'), $answer);
        $this->assertSame(1, $gateway->get_generate_calls());
        $this->assertFalse(\local_parce\local\question_handler::was_last_successful());
    }

    /**
     * The provider's no-answer sentinel is converted before any response decoration.
     */
    public function test_not_found_response_is_humanised(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $provider = new test_provider(true, 'fake', '[]', id: 1);
        $plan = new \local_parce\aiactions\responses\response_question_plan(true);
        $plan->set_response_data([
            'generatedcontent' => json_encode(['type' => 'content', 'params' => []]),
            'model' => 'fake-model',
        ]);
        $answer = new \local_parce\aiactions\responses\response_question_plan(true);
        $answer->set_response_data([
            'generatedcontent' => "  NOT_FOUND\n",
            'model' => 'fake-model',
        ]);
        $gateway = new test_ai_gateway([$plan, $answer], $provider);

        $result = \local_parce\local\question_handler::process(
            'Question without an answer',
            \context_system::instance(),
            $gateway
        );

        $this->assertSame(get_string('answer_notfound', 'local_parce'), $result);
        $this->assertStringNotContainsString('NOT_FOUND', $result);
        $this->assertFalse(\local_parce\local\question_handler::was_last_successful());
        $this->assertSame(2, $gateway->get_generate_calls());
    }

    /**
     * Retrieved resources provide a deterministic fallback when the provider cannot answer directly.
     */
    public function test_retrieved_content_suggestions_include_resource_links(): void {
        $content = json_encode([[
            'name' => 'Examples [Kamaleon]',
            'url' => 'https://example.test/course/view.php?id=13',
        ]]);
        $result = \local_parce\local\intent\content::format_search_results($content, 'content_suggestions');

        $this->assertStringStartsWith(get_string('content_suggestions', 'local_parce'), $result);
        $this->assertStringContainsString(
            '[Examples \\[Kamaleon\\]](https://example.test/course/view.php?id=13)',
            $result
        );
    }

    /**
     * Search records without explanatory text can be returned without answer generation.
     */
    public function test_link_only_search_results_are_detected(): void {
        $links = json_encode([
            [
                'name' => 'Environmental management',
                'url' => 'https://example.test/course/view.php?id=48',
                'content' => '',
            ],
            [
                'name' => 'Environmental management B',
                'url' => 'https://example.test/course/view.php?id=49',
                'content' => '',
            ],
        ]);
        $content = json_encode([[
            'name' => 'Factorisation',
            'url' => 'https://example.test/page/view.php?id=1',
            'content' => 'Factorisation decomposes an expression into simpler factors.',
        ]]);

        $this->assertTrue(\local_parce\local\intent\content::are_link_only_results($links));
        $this->assertFalse(\local_parce\local\intent\content::are_link_only_results($content));
    }

    /**
     * Course references are not appended when the generated answer already contains the course URL.
     */
    public function test_existing_response_links_are_not_repeated_as_references(): void {
        $url = 'https://example.test/course/view.php?id=48';
        $content = json_encode([[
            'coursename' => 'Environmental management',
            'courseurl' => $url,
        ]]);
        $method = new \ReflectionMethod(\local_parce\local\question_handler::class, 'build_course_references');

        $references = $method->invoke(null, $content, \context_system::instance(), 'See [' . $url . '](' . $url . ')');

        $this->assertSame('', $references);
    }

    /**
     * References point to the specific retrieved resource instead of its containing course.
     */
    public function test_references_prefer_retrieved_resource_links(): void {
        $resourceurl = 'https://example.test/mod/forum/discuss.php?d=29';
        $courseurl = 'https://example.test/course/view.php?id=8';
        $content = json_encode([[
            'name' => 'Are social networks a window to the world?',
            'url' => $resourceurl,
            'coursename' => 'Language arts',
            'courseurl' => $courseurl,
        ]]);
        $method = new \ReflectionMethod(\local_parce\local\question_handler::class, 'build_course_references');

        $references = $method->invoke(null, $content, \context_system::instance(), 'Generated answer');

        $this->assertStringContainsString($resourceurl, $references);
        $this->assertStringContainsString('Are social networks a window to the world?', $references);
        $this->assertStringNotContainsString($courseurl, $references);
    }

    /**
     * Longer planner phrases accept a relevant subject match without allowing unrelated single-term results.
     */
    public function test_content_keyword_matching_handles_generic_question_wording(): void {
        $method = new \ReflectionMethod(\local_parce\local\intent\content::class, 'get_search_keyword_score');
        $text = 'Las redes sociales ofrecen comunicación, información y oportunidades educativas.';

        $this->assertSame(2, $method->invoke(null, $text, ['cosas buenas redes sociales']));
        $this->assertSame(2, $method->invoke(null, 'Información sobre gestión ambiental', ['Gestión ambiental']));
        $this->assertSame(0, $method->invoke(null, 'Contenido general del curso', ['Gestión ambiental']));
        $this->assertSame(0, $method->invoke(null, $text, ['kamaelon']));
        $this->assertGreaterThan(
            $method->invoke(null, $text, ['ventajas redes sociales']),
            $method->invoke(null, 'Las ventajas de las redes sociales incluyen comunicación.', ['ventajas redes sociales'])
        );
    }

    /**
     * Guest AI actions are durable audit records and link to the persisted turn.
     */
    public function test_guest_action_and_turn_are_persisted_for_audit(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setGuestUser();
        $guestid = guest_user()->id;
        $context = \context_system::instance();
        $provider = new test_provider(true, 'fake', '[]', id: 1);
        $response = new \local_parce\aiactions\responses\response_question_plan(true);
        $response->set_response_data([
            'generatedcontent' => json_encode(['type' => 'base', 'params' => []]),
            'model' => 'fake-model',
        ]);
        $gateway = new test_ai_gateway([$response], $provider);

        $answer = \local_parce\local\question_handler::process('Guest question', $context, $gateway);
        $actionids = \local_parce\local\question_handler::get_last_action_ids();
        $this->assertNotEmpty($answer);
        $this->assertTrue(\local_parce\local\question_handler::was_last_successful());
        $this->assertSame('success', \local_parce\local\question_handler::get_last_result()['status']);
        $this->assertCount(1, $actionids);

        $action = $DB->get_record('local_parce_ai_actions', ['id' => $actionids[0]], '*', MUST_EXIST);
        $this->assertSame((int) $guestid, (int) $action->userid);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $action->conversationkey);

        $entryid = \local_parce\local\controller::store_conversation(
            $guestid,
            $context->id,
            'Guest question',
            $answer,
            $actionids
        );
        $this->assertGreaterThan(0, $entryid);
        $this->assertSame(
            $entryid,
            (int) $DB->get_field('local_parce_ai_actions', 'conversationentryid', ['id' => $actionids[0]], MUST_EXIST)
        );
    }
}
