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

/**
 * Authorization tests for the answer endpoint.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(answer::class)]
final class answer_test extends \core_external\tests\externallib_testcase {
    /**
     * The external contract preserves legacy fields and exposes structured failures.
     */
    public function test_missing_provider_returns_structured_result_without_persisting_turn(): void {
        global $DB;

        $this->resetAfterTest();
        set_config('enabled', 1, 'local_parce');
        $this->setAdminUser();

        $result = answer::execute('Question', \context_system::instance()->id);
        $cleanresult = \core_external\external_api::clean_returnvalue(answer::execute_returns(), $result);

        $this->assertArrayHasKey('answer', $cleanresult);
        $this->assertArrayHasKey('newconversation', $cleanresult);
        $this->assertArrayHasKey('usagepercentage', $cleanresult);
        $this->assertSame('error', $cleanresult['status']);
        $this->assertFalse($cleanresult['successful']);
        $this->assertTrue($cleanresult['retryable']);
        $this->assertSame('ai_unavailable', $cleanresult['errorcode']);
        $this->assertArrayNotHasKey('retryafter', $cleanresult);
        $this->assertSame(0, $DB->count_records('local_parce_conversation_entries'));
        $this->assertSame([], \local_parce\local\controller::get_conversation_entries(
            get_admin()->id,
            \context_system::instance()->id
        ));
    }

    /**
     * A failed question cannot commit a provisional conversation rollover.
     */
    public function test_failed_question_restores_conversation_at_limit(): void {
        $this->resetAfterTest();
        set_config('enabled', 1, 'local_parce');
        set_config('cache_maxentries', 40, 'local_parce');
        set_config('conversation_maxtokens', 10, 'local_parce');
        $this->setAdminUser();
        $adminid = get_admin()->id;
        $contextid = \context_system::instance()->id;
        \local_parce\local\controller::store_conversation_entry(
            $adminid,
            $contextid,
            '1',
            '1'
        );
        $entriesbefore = \local_parce\local\controller::get_conversation_entries($adminid, $contextid);
        $keybefore = \local_parce\local\controller::generate_conversation_key($adminid, $contextid);

        $result = answer::execute('123456789012345678901234567890', $contextid);

        $this->assertFalse($result['successful']);
        $this->assertFalse($result['newconversation']);
        $this->assertSame(
            $entriesbefore,
            \local_parce\local\controller::get_conversation_entries($adminid, $contextid)
        );
        $this->assertSame(
            $keybefore,
            \local_parce\local\controller::generate_conversation_key($adminid, $contextid)
        );
    }

    /**
     * Access must be allowed in both the requested and canonical chat contexts.
     */
    public function test_canonical_context_permission_is_required(): void {
        $this->resetAfterTest();
        set_config('enabled', 1, 'local_parce');
        [$user, $coursecontext, $modulecontext, $roleid] = $this->create_context_fixture();
        assign_capability('local/parce:usechat', CAP_PREVENT, $roleid, $coursecontext->id);
        assign_capability('local/parce:usechat', CAP_ALLOW, $roleid, $modulecontext->id);
        $this->setUser($user);

        $this->assertTrue(has_capability('local/parce:usechat', $modulecontext));
        $this->assertFalse(has_capability('local/parce:usechat', $coursecontext));
        $this->expectException(\required_capability_exception::class);

        answer::execute('Question', $modulecontext->id);
    }

    /**
     * Permission in the canonical context cannot bypass denial in the requested context.
     */
    public function test_requested_context_permission_is_required(): void {
        $this->resetAfterTest();
        set_config('enabled', 1, 'local_parce');
        [$user, $coursecontext, $modulecontext, $roleid] = $this->create_context_fixture();
        assign_capability('local/parce:usechat', CAP_ALLOW, $roleid, $coursecontext->id);
        assign_capability('local/parce:usechat', CAP_PREVENT, $roleid, $modulecontext->id);
        $this->setUser($user);

        $this->assertTrue(has_capability('local/parce:usechat', $coursecontext));
        $this->assertFalse(has_capability('local/parce:usechat', $modulecontext));
        $this->expectException(\required_capability_exception::class);

        answer::execute('Question', $modulecontext->id);
    }

    /**
     * Create a user with a role assigned in a course containing one module.
     *
     * @return array User, course context, module context and role ID.
     */
    private function create_context_fixture(): array {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $module = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $coursecontext = \context_course::instance($course->id);
        $modulecontext = \context_module::instance($module->cmid);
        $roleid = $this->getDataGenerator()->create_role();
        role_assign($roleid, $user->id, $coursecontext->id);

        return [$user, $coursecontext, $modulecontext, $roleid];
    }
}
