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

namespace local_parce\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;

/**
 * Tests for the privacy provider.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Metadata declares both database tables and the external AI provider.
     */
    public function test_metadata(): void {
        $collection = new \core_privacy\local\metadata\collection('local_parce');
        $items = provider::get_metadata($collection)->get_collection();

        $this->assertCount(3, $items);
        $this->assertInstanceOf(\core_privacy\local\metadata\types\database_table::class, $items[0]);
        $this->assertInstanceOf(\core_privacy\local\metadata\types\database_table::class, $items[1]);
        $this->assertInstanceOf(\core_privacy\local\metadata\types\external_location::class, $items[2]);
    }

    /**
     * Context discovery and deletion include conversation and AI trace data.
     */
    public function test_context_discovery_and_user_deletion(): void {
        global $DB;

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $this->setUser($user);
        $entryid = \local_parce\local\controller::store_conversation_entry(
            $user->id,
            $context->id,
            'Question',
            '<p>Answer</p>'
        );
        $key = $DB->get_field('local_parce_conversation_entries', 'conversationkey', ['id' => $entryid], MUST_EXIST);
        $DB->insert_record('local_parce_ai_actions', (object) [
            'userid' => $user->id,
            'contextid' => $context->id,
            'chatid' => $context->id,
            'conversationkey' => $key,
            'conversationentryid' => $entryid,
            'actiontype' => 'question_plan',
            'prompt' => 'Prompt',
            'prompttext' => 'Question',
            'success' => 1,
            'timecreated' => time(),
        ]);

        $contexts = provider::get_contexts_for_userid($user->id)->get_contextids();
        $this->assertEquals([$context->id], $contexts);
        $this->assertNotEmpty(\local_parce\local\controller::get_conversation_entries($user->id, $context->id));

        $approved = new approved_contextlist($user, 'local_parce', [$context->id]);
        provider::delete_data_for_user($approved);
        $this->assertFalse($DB->record_exists('local_parce_ai_actions', ['userid' => $user->id]));
        $this->assertFalse($DB->record_exists('local_parce_conversation_entries', ['userid' => $user->id]));
        $this->assertSame([], \local_parce\local\controller::get_conversation_entries($user->id, $context->id));
    }

    /**
     * Correlation and metric fields are included in the user's Privacy export.
     */
    public function test_export_includes_ai_trace_metadata(): void {
        global $DB;

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $context = \context_system::instance();
        $requestid = str_repeat('a', 64);
        $callid = str_repeat('b', 64);
        $DB->insert_record('local_parce_ai_actions', (object) [
            'userid' => $user->id,
            'contextid' => $context->id,
            'chatid' => $context->id,
            'conversationkey' => str_repeat('c', 64),
            'requestid' => $requestid,
            'callid' => $callid,
            'attemptordinal' => 1,
            'actiontype' => 'question_plan',
            'prompt' => 'Prompt',
            'prompttext' => 'Question',
            'success' => 1,
            'status' => 'completed',
            'outcome' => 'success',
            'completionreason' => 'success',
            'durationms' => 25,
            'providercomponent' => 'aiprovider_test',
            'providerinstanceid' => 7,
            'providername' => 'Test provider',
            'prompttokens' => 10,
            'completiontokens' => 4,
            'timecreated' => time(),
            'timecompleted' => time(),
        ]);

        writer::reset();
        provider::export_user_data(new approved_contextlist($user, 'local_parce', [$context->id]));
        $data = writer::with_context($context)->get_data([
            get_string('pluginname', 'local_parce'),
            get_string('privacy:metadata:ai_actions', 'local_parce'),
        ]);
        $this->assertCount(1, $data->actions);
        $trace = reset($data->actions);
        $this->assertSame($requestid, $trace->requestid);
        $this->assertSame($callid, $trace->callid);
        $this->assertSame(25, (int) $trace->durationms);
        $this->assertSame(10, (int) $trace->prompttokens);
        $this->assertSame('aiprovider_test', $trace->providercomponent);
    }
}
