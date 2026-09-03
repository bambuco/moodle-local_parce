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
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_parce\local\history_cursor;
use local_parce\local\history_repository;
use local_parce\local\history_service;

/**
 * List conversations in a context.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class list_history_conversations extends external_api {
    /**
     * Get the parameters for executing the function to list history conversations.
     *
     * @return external_function_parameters The parameters for the function.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'chatid' => new external_value(PARAM_INT, 'Context ID'),
            'userid' => new external_value(PARAM_INT, 'Target user; zero means all users in admin mode', VALUE_DEFAULT, 0),
            'mode' => new external_value(PARAM_ALPHA, 'own or admin', VALUE_DEFAULT, 'own'),
            'cursor' => new external_value(PARAM_RAW, 'Opaque continuation cursor', VALUE_DEFAULT, ''),
            'limit' => new external_value(PARAM_INT, 'Page size; zero uses the configured maximum', VALUE_DEFAULT, 0),
            'conversationkey' => new external_value(PARAM_ALPHANUM, 'Optional exact conversation key', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Execute the function to list history conversations.
     *
     * @param int $chatid Context ID.
     * @param int $userid Target user; zero means all users in admin mode.
     * @param string $mode 'own' or 'admin'.
     * @param string $cursor Opaque continuation cursor.
     * @param int $limit Page size; zero uses the configured maximum.
     * @param string $conversationkey Optional exact conversation key.
     * @return array List of history conversations.
     * @throws \invalid_parameter_exception If the parameters are invalid.
     */
    public static function execute(
        int $chatid,
        int $userid = 0,
        string $mode = 'own',
        string $cursor = '',
        int $limit = 0,
        string $conversationkey = ''
    ): array {
        global $USER;

        $params = self::validate_parameters(
            self::execute_parameters(),
            compact('chatid', 'userid', 'mode', 'cursor', 'limit', 'conversationkey')
        );
        history_service::require_access(max(1, $params['limit']));
        $limit = history_service::limit($params['limit'], 'history_conversation_limit');
        if (!in_array($params['mode'], ['own', 'admin'], true)) {
            throw new \invalid_parameter_exception('Invalid history request.');
        }
        $admin = $params['mode'] === 'admin';
        $target = $admin ? ($params['userid'] ?: null) : (int) $USER->id;
        $context = history_service::context($params['chatid'], $admin || ($target !== (int) $USER->id));
        // Validate the web-service request at system level. Enrolment is not an
        // access condition for history; privileged access is checked explicitly above.
        self::validate_context(\context_system::instance());
        $scope = ['target' => $target ?: 0, 'mode' => $params['mode'], 'chatid' => $params['chatid'],
            'filters' => ['conversationkey' => $params['conversationkey']]];
        $state = $params['cursor'] === '' ? ['snapshot' => history_service::snapshot(), 'after' => null]
            : history_cursor::decode($params['cursor'], 'conversations', $scope);
        $records = history_repository::conversations(
            $params['chatid'],
            $target,
            (int) $state['snapshot'],
            $state['after'],
            $limit,
            $params['conversationkey'] ?: null
        );
        $hasmore = count($records) > $limit;
        if ($hasmore) {
            array_pop($records);
        }
        $usage = history_repository::conversation_usage($params['chatid'], (int) $state['snapshot'], $records);
        $items = [];
        $guestid = (int) guest_user()->id;
        foreach ($records as $record) {
            $isguest = (int) $record->userid === $guestid;
            $user = $isguest ? null : \core_user::get_user((int) $record->userid, '*', IGNORE_MISSING);
            $conversationusage = $usage[$record->userid . '|' . $record->conversationkey] ?? null;
            $items[] = ['userid' => (int) $record->userid, 'conversationkey' => $record->conversationkey,
                'lastactivity' => (int) $record->lastactivity, 'turncount' => (int) $record->turncount,
                'prompttokens' => (int) ($conversationusage->prompttokens ?? 0),
                'completiontokens' => (int) ($conversationusage->completiontokens ?? 0),
                'isguest' => $isguest,
                'displayname' => $isguest ? get_string('historyguestsession', 'local_parce')
                    : ($user ? fullname($user) : get_string('historyunavailableuser', 'local_parce'))];
        }
        if ($admin) {
            $keysbyuser = [];
            foreach ($items as $item) {
                $keysbyuser[$item['userid']][] = $item['conversationkey'];
            }
            history_service::audit($context, array_column($items, 'userid'), $keysbyuser);
        }
        $next = '';
        if ($hasmore && $records) {
            $last = end($records);
            $next = history_cursor::encode('conversations', $scope, ['snapshot' => (int) $state['snapshot'],
                'after' => [(int) $last->lastactivity, (int) $last->userid, $last->conversationkey]]);
        }
        return ['conversations' => $items, 'cursor' => $next, 'hasmore' => $hasmore,
            'limited' => count($items) >= $limit, 'resultlimit' => $limit];
    }

    /**
     * Returns the structure of the list history conversations service.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'conversations' => new external_multiple_structure(new external_single_structure([
                'userid' => new external_value(PARAM_INT, 'Conversation owner'),
                'conversationkey' => new external_value(PARAM_ALPHANUM, 'Conversation key'),
                'lastactivity' => new external_value(PARAM_INT, 'Last turn time'),
                'turncount' => new external_value(PARAM_INT, 'Turn count'),
                'prompttokens' => new external_value(PARAM_INT, 'Prompt tokens'),
                'completiontokens' => new external_value(PARAM_INT, 'Completion tokens'),
                'isguest' => new external_value(PARAM_BOOL, 'Whether this is a shared guest session'),
                'displayname' => new external_value(PARAM_TEXT, 'Safe owner label'),
            ])),
            'cursor' => new external_value(PARAM_RAW, 'Next cursor'),
            'hasmore' => new external_value(PARAM_BOOL, 'More results'),
            'limited' => new external_value(PARAM_BOOL, 'Whether the configured maximum was displayed'),
            'resultlimit' => new external_value(PARAM_INT, 'Configured result maximum'),
        ]);
    }
}
