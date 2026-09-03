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

/**
 * Tests for persistent conversation history access.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(get_conversation::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(get_active_conversation::class)]
final class get_conversation_test extends \core_external\tests\externallib_testcase {
    /**
     * A user can read their own history without current course enrolment.
     */
    public function test_user_can_read_own_history_after_unenrolment(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $this->create_turn($user->id, $context->id, 'Question', '<p>Answer</p>');

        $this->setUser($user);
        $result = get_conversation::execute($context->id);
        $result = external_api::clean_returnvalue(get_conversation::execute_returns(), $result);

        $this->assertSame(1, $result['total']);
        $this->assertCount(2, $result['entries']);
        $this->assertSame('Question', $result['entries'][0]['content']);
        $this->assertSame('<p>Answer</p>', $result['entries'][1]['content']);
    }

    /**
     * Viewing another user's history checks the capability in the chat context and emits an event.
     */
    public function test_privileged_history_view_uses_chat_context_and_logs_event(): void {
        $this->resetAfterTest();
        $viewer = $this->getDataGenerator()->create_user();
        $target = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $this->create_turn($target->id, $context->id, 'Question', '<p>Answer</p>');

        $roleid = $this->getDataGenerator()->create_role();
        role_assign($roleid, $viewer->id, $context->id);
        assign_capability('local/parce:viewallchats', CAP_ALLOW, $roleid, $context->id);
        $this->setUser($viewer);

        $sink = $this->redirectEvents();
        $result = get_conversation::execute($context->id, $target->id);
        $result = external_api::clean_returnvalue(get_conversation::execute_returns(), $result);
        $events = $sink->get_events();

        $this->assertSame(1, $result['total']);
        $this->assertCount(1, $events);
        $this->assertInstanceOf(\local_parce\event\conversation_history_viewed::class, $events[0]);
        $this->assertSame($context->id, $events[0]->contextid);
        $this->assertSame($target->id, $events[0]->relateduserid);
    }

    /**
     * Guest users cannot read persistent history.
     */
    public function test_guest_cannot_read_history(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $this->setGuestUser();

        $this->expectException(\moodle_exception::class);
        get_conversation::execute($context->id);
    }

    /**
     * Enabled guests can read only their active session conversation.
     */
    public function test_enabled_guest_can_read_active_conversation(): void {
        $this->resetAfterTest();
        set_config('enabled', 1, 'local_parce');
        set_config('enable_guests', 1, 'local_parce');
        $this->setGuestUser();

        $result = get_active_conversation::execute(\context_system::instance()->id);
        $result = external_api::clean_returnvalue(get_active_conversation::execute_returns(), $result);

        $this->assertSame(0, $result['total']);
        $this->assertSame([], $result['entries']);
        $this->assertSame(0, $result['usagepercentage']);
    }

    /**
     * Reaching the configured turn limit starts a new conversation key.
     */
    public function test_turn_limit_starts_new_conversation(): void {
        $this->resetAfterTest();
        set_config('cache_maxentries', 1, 'local_parce');
        $user = $this->getDataGenerator()->create_user();
        $context = \context_system::instance();
        $this->setUser($user);

        \local_parce\local\controller::store_conversation_entry(
            $user->id,
            $context->id,
            'First question',
            '<p>First answer</p>'
        );
        $firstkey = $this->get_conversation_key($user->id, $context->id);
        $secondkey = \local_parce\local\controller::generate_conversation_key($user->id, $context->id);

        $this->assertNotSame($firstkey, $secondkey);
        $this->assertSame([], \local_parce\local\controller::get_conversation_entries($user->id, $context->id));
    }

    /**
     * A question that would reach the estimated-token limit starts a new conversation.
     */
    public function test_estimated_token_limit_starts_new_conversation_before_question(): void {
        $this->resetAfterTest();
        set_config('cache_maxentries', 40, 'local_parce');
        set_config('conversation_maxtokens', 10, 'local_parce');
        $user = $this->getDataGenerator()->create_user();
        $context = \context_system::instance();
        $this->setUser($user);

        \local_parce\local\controller::store_conversation_entry(
            $user->id,
            $context->id,
            '123456',
            '123456'
        );
        $rotated = \local_parce\local\controller::prepare_conversation(
            $user->id,
            $context->id,
            '123456789012345678'
        );

        $this->assertTrue($rotated);
        $this->assertSame([], \local_parce\local\controller::get_conversation_entries($user->id, $context->id));
        $this->assertSame(0, \local_parce\local\controller::get_conversation_usage($user->id, $context->id)['percentage']);
    }

    /**
     * Prompt context contains only the eight most recent complete cached turns.
     */
    public function test_prompt_context_uses_recent_complete_cached_turns_only(): void {
        $this->resetAfterTest();
        set_config('cache_maxentries', 20, 'local_parce');
        set_config('conversation_maxtokens', 16000, 'local_parce');
        $user = $this->getDataGenerator()->create_user();
        $context = \context_system::instance();
        $this->setUser($user);

        for ($turn = 1; $turn <= 9; $turn++) {
            \local_parce\local\controller::store_conversation_entry(
                $user->id,
                $context->id,
                "Question {$turn}",
                "<p>Answer {$turn}</p>"
            );
        }

        $contextentries = \local_parce\local\controller::get_prompt_context($user->id, $context->id);
        $this->assertCount(16, $contextentries);
        $this->assertSame('Question 2', $contextentries[0]['content']);
        $this->assertSame('<p>Answer 9</p>', $contextentries[15]['content']);

        // A durable-only turn must never be read back into the active prompt.
        \local_parce\local\controller::clear_conversation($user->id, $context->id);
        $this->assertSame([], \local_parce\local\controller::get_prompt_context($user->id, $context->id));
    }

    /**
     * Active conversation service returns the complete conversation chronologically.
     */
    public function test_active_conversation_is_not_paginated_or_split(): void {
        $this->resetAfterTest();
        $user = get_admin();
        $context = \context_system::instance();
        $this->setUser($user);
        \local_parce\local\controller::store_conversation_entry($user->id, $context->id, 'One', 'Answer one');
        \local_parce\local\controller::store_conversation_entry($user->id, $context->id, 'Two', 'Answer two');

        set_config('enabled', 1, 'local_parce');
        $result = get_active_conversation::execute($context->id);
        $result = external_api::clean_returnvalue(get_active_conversation::execute_returns(), $result);

        $this->assertSame(4, $result['total']);
        $this->assertSame(['One', 'Answer one', 'Two', 'Answer two'], array_column($result['entries'], 'content'));
        $this->assertArrayNotHasKey('hasmore', $result);
        $this->assertArrayNotHasKey('offset', $result);
    }

    /**
     * Server-cleaned response HTML remains identical when restored by the widget endpoint.
     */
    public function test_response_html_is_clean_when_restored(): void {
        $this->resetAfterTest();
        $user = get_admin();
        $context = \context_system::instance();
        $this->setUser($user);
        set_config('enabled', 1, 'local_parce');

        $unsafe = '<script>alert(1)</script><img src=x onerror="alert(2)">'
            . '[unsafe](javascript:alert(3)) **Safe text**';
        $clean = format_text($unsafe, FORMAT_MARKDOWN, [
            'noclean' => false,
            'para' => false,
            'filter' => false,
        ]);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringContainsString('<strong>Safe text</strong>', $clean);

        \local_parce\local\controller::store_conversation_entry($user->id, $context->id, 'Question', $clean);
        $result = get_active_conversation::execute($context->id);
        $result = external_api::clean_returnvalue(get_active_conversation::execute_returns(), $result);

        $this->assertSame($clean, $result['entries'][1]['content']);
    }

    /**
     * Retrieved JSON remains valid Unicode and payloads respect both budgets.
     */
    public function test_retrieved_json_and_payload_budgets(): void {
        $items = [
            ['name' => 'Lección ágil 🚀', 'content' => str_repeat('contenido ñ ', 5000)],
            ['name' => 'Segundo', 'content' => str_repeat('más ', 5000)],
        ];
        $json = \local_parce\local\controller::encode_retrieved_items($items, 100);
        $this->assertIsArray(json_decode($json, true, flags: JSON_THROW_ON_ERROR));
        $this->assertLessThanOrEqual(100, \local_parce\local\controller::estimate_payload_tokens($json));

        $payload = \local_parce\local\controller::build_ai_payload(
            'Question',
            [['role' => 'user', 'content' => str_repeat('old ', 30000)]],
            \local_parce\local\controller::encode_retrieved_items($items)
        );
        $this->assertStringNotContainsString('Instruction', $payload);
        $this->assertStringNotContainsString('<INSTRUCTION_START>', $payload);
        $this->assertLessThanOrEqual(
            \local_parce\local\controller::MAX_PAYLOAD_TOKENS,
            \local_parce\local\controller::estimate_payload_tokens($payload)
        );
    }

    /**
     * Guest turns remain active and are persisted for audit under their conversation key.
     */
    public function test_guest_turn_is_persisted_for_audit(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setGuestUser();
        $guestid = guest_user()->id;
        $chatid = \context_system::instance()->id;

        $entryid = \local_parce\local\controller::store_conversation(
            $guestid,
            $chatid,
            'Guest question',
            'Guest answer'
        );

        $this->assertCount(2, \local_parce\local\controller::get_conversation_entries($guestid, $chatid));
        $this->assertGreaterThan(0, $entryid);
        $record = $DB->get_record('local_parce_conversation_entries', ['id' => $entryid], '*', MUST_EXIST);
        $this->assertSame((int) $guestid, (int) $record->userid);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $record->conversationkey);
    }

    /**
     * Invalid stored conversation limits are rejected rather than silently clamped.
     */
    public function test_invalid_conversation_limits_are_rejected(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $chatid = \context_system::instance()->id;
        $this->setUser($user);

        set_config('cache_maxentries', 41, 'local_parce');
        try {
            \local_parce\local\controller::get_conversation_usage($user->id, $chatid);
            $this->fail('An excessive turn limit must be rejected.');
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('cache_maxentries', $e->getMessage());
        }

        set_config('cache_maxentries', 40, 'local_parce');
        set_config('conversation_maxtokens', 0, 'local_parce');
        $this->expectException(\coding_exception::class);
        $this->expectExceptionMessage('conversation_maxtokens');
        \local_parce\local\controller::get_conversation_usage($user->id, $chatid);
    }

    /**
     * Privacy invalidation makes an existing session snapshot unreadable.
     */
    public function test_global_privacy_token_invalidates_active_cache(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $context = \context_system::instance();
        $this->setUser($user);
        \local_parce\local\controller::store_conversation_entry($user->id, $context->id, 'Private', 'Data');

        \local_parce\local\controller::invalidate_active_conversations();

        $this->assertSame([], \local_parce\local\controller::get_conversation_entries($user->id, $context->id));
    }

    /**
     * A privacy generation change invalidates snapshots created by another PHP session.
     */
    public function test_privacy_token_invalidates_another_session(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $chatid = \context_system::instance()->id;

        \cache_phpunit_session::phpunit_mockup_session_id('session-one');
        $this->setUser($user);
        \local_parce\local\controller::store_conversation_entry($user->id, $chatid, 'Private', 'Data');
        $this->assertCount(2, \local_parce\local\controller::get_conversation_entries($user->id, $chatid));

        \cache_factory::instance()->reset_cache_instances();
        \cache_phpunit_session::phpunit_mockup_session_id('session-two');
        $this->setUser($user);
        \local_parce\local\controller::invalidate_active_conversations();

        \cache_factory::instance()->reset_cache_instances();
        \cache_phpunit_session::phpunit_mockup_session_id('session-one');
        $this->setUser($user);
        $this->assertSame([], \local_parce\local\controller::get_conversation_entries($user->id, $chatid));
    }

    /**
     * Read a persisted conversation key.
     *
     * @param int $userid User ID
     * @param int $chatid Chat context ID
     * @return string
     */
    private function get_conversation_key(int $userid, int $chatid): string {
        global $DB;

        return $DB->get_field('local_parce_conversation_entries', 'conversationkey', [
            'userid' => $userid,
            'chatid' => $chatid,
        ], MUST_EXIST);
    }

    /**
     * Insert one completed conversation turn.
     *
     * @param int $userid User ID
     * @param int $chatid Chat context ID
     * @param string $question Question
     * @param string $response Response
     */
    private function create_turn(int $userid, int $chatid, string $question, string $response): void {
        global $DB;

        $DB->insert_record('local_parce_conversation_entries', (object) [
            'userid' => $userid,
            'chatid' => $chatid,
            'conversationkey' => hash('sha256', "{$userid}_{$chatid}"),
            'question' => $question,
            'response' => $response,
            'timecreated' => time(),
        ]);
    }
}
