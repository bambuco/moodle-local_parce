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

use local_parce\local\controller;

/**
 * Context choice, new threads and conversation summaries.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(controller::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(set_global_context::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(start_new_conversation::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(summarise_new_conversation::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(answer::class)]
final class context_services_test extends \advanced_testcase {
    /**
     * The first question of a course thread can be answered with the site context.
     */
    public function test_first_question_uses_site_context_without_leaving_the_course_chat(): void {
        global $DB;

        $this->resetAfterTest();
        set_config('enabled', 1, 'local_parce');
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);

        $result = answer::execute('Question about the site', $coursecontext->id, true);

        $this->assertFalse($result['successful']);
        $this->assertFalse($result['globalcontext']);
        $trace = $DB->get_record('local_parce_ai_actions', [], '*', MUST_EXIST);
        $this->assertEquals(\context_system::instance()->id, $trace->contextid);
        $this->assertEquals($coursecontext->id, $trace->chatid);
        $this->assertSame(0, $DB->count_records('local_parce_conversation_entries'));
    }

    /**
     * A started thread keeps its stored context when the client sends the other value.
     */
    public function test_later_question_ignores_a_different_context_request(): void {
        global $DB;

        $this->resetAfterTest();
        set_config('enabled', 1, 'local_parce');
        $this->setAdminUser();
        $adminid = get_admin()->id;
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        $this->assertTrue(controller::set_global_context($adminid, $coursecontext->id, true));
        controller::store_conversation_entry($adminid, $coursecontext->id, 'Question', 'Answer');
        $key = controller::generate_conversation_key($adminid, $coursecontext->id);

        answer::execute('Follow up', $coursecontext->id, false);

        $trace = $DB->get_record('local_parce_ai_actions', ['chatid' => $coursecontext->id], '*', MUST_EXIST);
        $this->assertEquals(\context_system::instance()->id, $trace->contextid);
        $this->assertTrue(controller::get_global_context($adminid, $coursecontext->id));
        $this->assertSame($key, controller::generate_conversation_key($adminid, $coursecontext->id));
    }

    /**
     * The choice cannot be changed once the thread has entries, or from the site.
     */
    public function test_context_choice_is_rejected_after_the_thread_starts_and_on_the_site(): void {
        $this->resetAfterTest();
        set_config('enabled', 1, 'local_parce');
        $this->setAdminUser();
        $adminid = get_admin()->id;
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        $this->assertTrue(set_global_context::execute($coursecontext->id, true)['accepted']);
        $key = controller::generate_conversation_key($adminid, $coursecontext->id);
        controller::store_conversation_entry($adminid, $coursecontext->id, 'Question', 'Answer');

        $rejected = set_global_context::execute($coursecontext->id, false);

        $this->assertFalse($rejected['accepted']);
        $this->assertTrue($rejected['globalcontext']);
        $this->assertSame($key, controller::generate_conversation_key($adminid, $coursecontext->id));
        $this->assertFalse(set_global_context::execute(\context_system::instance()->id, true)['accepted']);
        $this->assertFalse(controller::apply_requested_global_context(
            $adminid,
            \context_system::instance()->id,
            \context_system::instance(),
            true,
            false
        ));
    }

    /**
     * An empty conversation starts with the page context. A limit rollover does too.
     */
    public function test_new_and_rotated_conversations_start_with_the_page_context(): void {
        global $DB;

        $this->resetAfterTest();
        set_config('enabled', 1, 'local_parce');
        set_config('conversation_maxtokens', 10, 'local_parce');
        $this->setAdminUser();
        $adminid = get_admin()->id;
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        controller::set_global_context($adminid, $coursecontext->id, true);
        controller::store_conversation_entry($adminid, $coursecontext->id, '12345678901234567890', 'Answer');
        $key = controller::generate_conversation_key($adminid, $coursecontext->id);
        $stored = $DB->count_records('local_parce_conversation_entries', ['userid' => $adminid]);

        $empty = start_new_conversation::execute($coursecontext->id);

        $this->assertTrue($empty['rotated']);
        $this->assertFalse($empty['globalcontext']);
        $this->assertSame([], controller::get_conversation_entries($adminid, $coursecontext->id));
        $this->assertNotSame($key, controller::generate_conversation_key($adminid, $coursecontext->id));
        $this->assertSame($stored, $DB->count_records('local_parce_conversation_entries', ['userid' => $adminid]));

        controller::store_conversation_entry($adminid, $coursecontext->id, '12345678901234567890', 'Answer');
        $snapshot = null;
        $rotated = controller::prepare_conversation(
            $adminid,
            $coursecontext->id,
            '12345678901234567890',
            $snapshot
        );
        $this->assertTrue($rotated);
        $this->assertFalse(controller::apply_requested_global_context(
            $adminid,
            $coursecontext->id,
            $coursecontext,
            true,
            true
        ));
        $this->assertFalse(controller::get_global_context($adminid, $coursecontext->id));
        $this->assertSame([], controller::get_conversation_entries($adminid, $coursecontext->id));
    }

    /**
     * A failed summary leaves the active thread in place.
     */
    public function test_summary_failure_does_not_rotate_and_empty_thread_is_rejected(): void {
        $this->resetAfterTest();
        set_config('enabled', 1, 'local_parce');
        $this->setAdminUser();
        $adminid = get_admin()->id;
        $context = \context_system::instance();

        $empty = summarise_new_conversation::execute($context->id);
        $this->assertFalse($empty['successful']);
        $this->assertSame('', $empty['summary']);

        controller::store_conversation_entry($adminid, $context->id, 'Question', 'Answer');
        $key = controller::generate_conversation_key($adminid, $context->id);
        $entries = controller::get_conversation_entries($adminid, $context->id);

        $failed = summarise_new_conversation::execute($context->id);

        $this->assertFalse($failed['successful']);
        $this->assertSame($key, controller::generate_conversation_key($adminid, $context->id));
        $this->assertEquals($entries, controller::get_conversation_entries($adminid, $context->id));
    }

    /**
     * The summary is the only visible message and stays ahead of later turns in the prompt.
     */
    public function test_summary_opens_the_new_thread_and_keeps_its_context(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $adminid = get_admin()->id;
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        controller::set_global_context($adminid, $coursecontext->id, true);
        controller::store_conversation_entry($adminid, $coursecontext->id, 'Old question', 'Old answer');

        $stored = controller::store_opening_summary(
            $adminid,
            $coursecontext->id,
            true,
            '<p>A short summary.</p>',
            [],
            controller::get_active_cache_version()
        );
        $this->assertTrue($stored);

        $entries = controller::get_conversation_entries($adminid, $coursecontext->id);
        $this->assertCount(1, $entries);
        $this->assertSame('system', $entries[0]['role']);
        $this->assertSame('<p>A short summary.</p>', $entries[0]['content']);
        $this->assertTrue(controller::get_global_context($adminid, $coursecontext->id));

        controller::store_conversation_entry($adminid, $coursecontext->id, 'Next question', 'Next answer');
        $prompt = controller::get_prompt_context($adminid, $coursecontext->id);
        $this->assertSame('<p>A short summary.</p>', $prompt[0]['content']);
        $this->assertSame('system', $prompt[0]['role']);
        $this->assertSame('Next question', $prompt[1]['content']);
    }

    /**
     * Transcripts that exceed the payload budget keep the newest turns.
     */
    public function test_summary_transcript_drops_the_oldest_turns_first(): void {
        $entries = [
            ['role' => 'user', 'content' => str_repeat('old ', 20000)],
            ['role' => 'system', 'content' => 'Recent answer'],
        ];

        $transcript = controller::build_summary_transcript($entries);

        $this->assertStringContainsString('Recent answer', $transcript);
        $this->assertStringNotContainsString('old old', $transcript);
    }

    /**
     * The front page is site level. A real course is not.
     */
    public function test_site_level_includes_the_front_page_only(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $this->assertTrue(controller::is_site_level(\context_system::instance()));
        $this->assertTrue(controller::is_site_level(\context_course::instance(SITEID)));
        $this->assertFalse(controller::is_site_level(\context_course::instance($course->id)));
    }
}
