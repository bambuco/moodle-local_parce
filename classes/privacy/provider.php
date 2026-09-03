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

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy API implementation for local_parce.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe stored and transmitted personal data.
     *
     * @param collection $collection Metadata collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_parce_conversation_entries', [
            'userid' => 'privacy:metadata:conversation_entries:userid',
            'chatid' => 'privacy:metadata:conversation_entries:chatid',
            'conversationkey' => 'privacy:metadata:conversation_entries:conversationkey',
            'question' => 'privacy:metadata:conversation_entries:question',
            'response' => 'privacy:metadata:conversation_entries:response',
            'timecreated' => 'privacy:metadata:conversation_entries:timecreated',
        ], 'privacy:metadata:conversation_entries');

        $technical = 'privacy:metadata:ai_actions:technical';
        $collection->add_database_table('local_parce_ai_actions', [
            'userid' => 'privacy:metadata:ai_actions:userid',
            'contextid' => 'privacy:metadata:ai_actions:contextid',
            'chatid' => 'privacy:metadata:conversation_entries:chatid',
            'conversationkey' => 'privacy:metadata:ai_actions:conversationkey',
            'requestid' => $technical,
            'callid' => $technical,
            'attemptordinal' => $technical,
            'conversationentryid' => 'privacy:metadata:ai_actions:conversationentryid',
            'actiontype' => $technical,
            'intent' => $technical,
            'intentparams' => $technical,
            'prompt' => 'privacy:metadata:ai_actions:prompt',
            'prompttext' => 'privacy:metadata:ai_actions:prompttext',
            'generatedcontent' => 'privacy:metadata:ai_actions:generatedcontent',
            'success' => $technical,
            'status' => $technical,
            'outcome' => $technical,
            'completionreason' => $technical,
            'durationms' => $technical,
            'errorcode' => $technical,
            'errormessage' => $technical,
            'responseid' => $technical,
            'fingerprint' => $technical,
            'finishreason' => $technical,
            'prompttokens' => $technical,
            'completiontokens' => $technical,
            'model' => $technical,
            'provider' => $technical,
            'providercomponent' => $technical,
            'providerinstanceid' => $technical,
            'providername' => $technical,
            'timecreated' => 'privacy:metadata:ai_actions:timecreated',
            'timecompleted' => $technical,
        ], 'privacy:metadata:ai_actions');

        $collection->add_external_location_link('aiprovider_bbco', [
            'question' => 'privacy:metadata:conversation_entries:question',
            'conversation' => 'privacy:metadata:ai_actions:prompttext',
            'coursecontent' => 'privacy:metadata:ai_actions:prompttext',
        ], 'privacy:metadata:aiprovider');

        return $collection;
    }

    /**
     * Get contexts containing data for a user.
     *
     * @param int $userid User ID
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = 'SELECT chatid FROM {local_parce_conversation_entries} WHERE userid = :conversationuserid
                UNION
                SELECT chatid FROM {local_parce_ai_actions} WHERE userid = :actionuserid';
        $contextlist->add_from_sql($sql, [
            'conversationuserid' => $userid,
            'actionuserid' => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Export a user's approved data.
     *
     * @param approved_contextlist $contextlist Approved contexts
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $conversations = $DB->get_records(
                'local_parce_conversation_entries',
                ['userid' => $userid, 'chatid' => $context->id],
                'timecreated, id'
            );
            if ($conversations) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_parce'), get_string('privacy:metadata:conversation_entries', 'local_parce')],
                    (object) ['turns' => array_values($conversations)]
                );
            }

            $actions = $DB->get_records(
                'local_parce_ai_actions',
                ['userid' => $userid, 'chatid' => $context->id],
                'timecreated, id'
            );
            if ($actions) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_parce'), get_string('privacy:metadata:ai_actions', 'local_parce')],
                    (object) ['actions' => array_values($actions)]
                );
            }
        }
    }

    /**
     * Delete all component data in a context.
     *
     * @param \context $context Context being deleted
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        $DB->delete_records('local_parce_ai_actions', ['chatid' => $context->id]);
        $DB->delete_records('local_parce_conversation_entries', ['chatid' => $context->id]);
        \local_parce\local\controller::invalidate_active_conversations();
    }

    /**
     * Delete approved data for one user.
     *
     * @param approved_contextlist $contextlist Approved contexts
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $conditions = ['userid' => $userid, 'chatid' => $context->id];
            $DB->delete_records('local_parce_ai_actions', $conditions);
            $DB->delete_records('local_parce_conversation_entries', $conditions);
        }
        \local_parce\local\controller::invalidate_active_conversations();
    }

    /**
     * Add users with data in a context to a user list.
     *
     * @param userlist $userlist User list
     */
    public static function get_users_in_context(userlist $userlist): void {
        $sql = 'SELECT userid FROM {local_parce_conversation_entries} WHERE chatid = :conversationchatid
                UNION
                SELECT userid FROM {local_parce_ai_actions} WHERE chatid = :actionchatid';
        $userlist->add_from_sql('userid', $sql, [
            'conversationchatid' => $userlist->get_context()->id,
            'actionchatid' => $userlist->get_context()->id,
        ]);
    }

    /**
     * Delete approved users' data in a context.
     *
     * @param approved_userlist $userlist Approved users
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        if ($userlist->count() > 0) {
            [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
            $params['chatid'] = $userlist->get_context()->id;
            $select = "userid $insql AND chatid = :chatid";
            $DB->delete_records_select('local_parce_ai_actions', $select, $params);
            $DB->delete_records_select('local_parce_conversation_entries', $select, $params);
        }
        \local_parce\local\controller::invalidate_active_conversations();
    }
}
