<?php
// This file is part of Moodle - http://moodle.org/

namespace local_parce\external;

/** Tests for the cursor-based history services. */
final class history_services_test extends \core_external\tests\externallib_testcase {
    /** Cursor signatures and operation scopes cannot be reused. */
    public function test_cursor_tamper_and_reuse_are_rejected(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $context = \context_system::instance();
        $this->turn($user->id, $context->id, 'a', 1);
        $this->turn($user->id, $context->id, 'b', 2);
        $page = list_history_conversations::execute($context->id, 0, 'own', '', 1);
        $this->assertTrue($page['limited']);
        $this->assertSame(1, $page['resultlimit']);

        try {
            get_history_turns::execute($context->id, 'a', 0, $page['cursor'], 1);
            $this->fail('An operation-reused cursor was accepted.');
        } catch (\invalid_parameter_exception $e) {
            $this->assertStringContainsString('Invalid cursor.', $e->getMessage());
        }

        $this->expectException(\invalid_parameter_exception::class);
        list_history_conversations::execute($context->id, 0, 'own', $page['cursor'] . 'x', 1);
    }

    /** Snapshot pagination separates keys and cleans stored content. */
    public function test_snapshot_key_separation_and_xss_cleaning(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $context = \context_system::instance();
        $first = $this->turn($user->id, $context->id, 'one', 1, '<script>x</script><b>safe</b>');
        $this->turn($user->id, $context->id, 'one', 2);
        $page = get_history_turns::execute($context->id, 'one', 0, '', 1);
        $this->turn($user->id, $context->id, 'one', 3);
        $second = get_history_turns::execute($context->id, 'one', 0, $page['cursor'], 1);

        $this->assertSame($first, $page['turns'][0]['id']);
        $this->assertCount(1, $second['turns']);
        $this->assertStringNotContainsString('<script', $page['turns'][0]['response']);
        $this->assertStringContainsString('<b>safe</b>', $page['turns'][0]['response']);
        $this->assertSame([], get_history_turns::execute($context->id, 'other')['turns']);
    }

    /** Administrative pages require context capability and audit each exposed target once. */
    public function test_admin_authorisation_and_event(): void {
        $this->resetAfterTest();
        $viewer = $this->getDataGenerator()->create_user();
        $target = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $this->turn($target->id, $context->id, 'key', 1);
        $this->setUser($viewer);
        try {
            list_history_conversations::execute($context->id, 0, 'admin');
            $this->fail('Administrative history was returned without capability.');
        } catch (\required_capability_exception $e) {
            $this->assertTrue(true);
        }
        $roleid = $this->getDataGenerator()->create_role();
        role_assign($roleid, $viewer->id, $context->id);
        assign_capability('local/parce:viewallchats', CAP_ALLOW, $roleid, $context->id);
        $sink = $this->redirectEvents();
        $result = list_history_conversations::execute($context->id, 0, 'admin');
        $this->assertCount(1, $result['conversations']);
        $this->assertCount(1, $sink->get_events());
        $this->assertSame((int) $target->id, (int) $sink->get_events()[0]->relateduserid);
        $this->assertSame('key', $sink->get_events()[0]->other['conversationkeys']);
    }

    /** Guests cannot use any persistent-history operation. */
    public function test_guest_cannot_list_history_contexts(): void {
        $this->resetAfterTest();
        $this->setGuestUser();

        $this->expectException(\moodle_exception::class);
        list_history_contexts::execute();
    }

    /** Missing contexts remain visible only to the owner with a safe label. */
    public function test_deleted_context_is_safe_for_owner_and_not_authorised_for_admin(): void {
        $this->resetAfterTest();
        $owner = $this->getDataGenerator()->create_user();
        $viewer = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $chatid = $context->id;
        $this->turn($owner->id, $chatid, 'deleted', 1);
        $context->delete();

        $this->setUser($owner);
        $result = list_history_contexts::execute();
        $this->assertSame($chatid, $result['contexts'][0]['chatid']);
        $this->assertSame('[Unavailable context]', $result['contexts'][0]['name']);
        $this->assertCount(1, list_history_conversations::execute($chatid)['conversations']);

        $this->setUser($viewer);
        $this->expectException(\invalid_parameter_exception::class);
        list_history_conversations::execute($chatid, 0, 'admin');
    }

    /** Search matches one visible phrase and returns only minimal grouped metadata. */
    public function test_search_history_uses_visible_content_and_configured_limit(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $context = \context_system::instance();
        $this->turn($user->id, $context->id, 'one', 1, '<p>The exact phrase is here.</p>');
        $this->turn($user->id, $context->id, 'one', 2, 'another turn');
        $this->turn($user->id, $context->id, 'two', 3, 'The exact phrase appears again.');
        set_config('history_search_limit', 1, 'local_parce');

        $result = search_history::execute('exact phrase');

        $this->assertCount(1, $result['contexts']);
        $this->assertCount(1, $result['contexts'][0]['conversations']);
        $this->assertSame('two', $result['contexts'][0]['conversations'][0]['conversationkey']);
        $this->assertSame(1, $result['contexts'][0]['conversations'][0]['turncount']);
        $this->assertArrayNotHasKey('question', $result['contexts'][0]['conversations'][0]);
        $this->assertTrue($result['limited']);
        $this->assertSame(1, $result['resultlimit']);
    }

    /** Insert a durable turn and return its id. */
    private function turn(int $userid, int $chatid, string $key, int $time, string $response = 'answer'): int {
        global $DB;
        return (int) $DB->insert_record('local_parce_conversation_entries', (object) [
            'userid' => $userid, 'chatid' => $chatid, 'conversationkey' => $key,
            'question' => 'question', 'response' => $response, 'timecreated' => $time,
        ]);
    }
}
