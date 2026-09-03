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
use local_parce\local\history_repository;
use local_parce\local\history_service;

/**
 * Search visible persistent conversation content by complete phrase.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class search_history extends external_api {
    /**
     * Parameters for the search history service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'query' => new external_value(PARAM_TEXT, 'Complete phrase to find'),
            'chatid' => new external_value(PARAM_INT, 'Context restriction; zero searches all own contexts', VALUE_DEFAULT, 0),
            'userid' => new external_value(PARAM_INT, 'Target user; zero means current user', VALUE_DEFAULT, 0),
            'mode' => new external_value(PARAM_ALPHA, 'own or admin', VALUE_DEFAULT, 'own'),
        ]);
    }

    /**
     * Execute the search history service.
     *
     * @param string $query
     * @param int $chatid
     * @param int $userid
     * @param string $mode
     * @return array
     */
    public static function execute(string $query, int $chatid = 0, int $userid = 0, string $mode = 'own'): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), compact('query', 'chatid', 'userid', 'mode'));
        history_service::require_access(1);
        $query = trim($params['query']);
        if ($query === '' || \core_text::strlen($query) > 255 || !in_array($params['mode'], ['own', 'admin'], true)) {
            throw new \invalid_parameter_exception('Invalid history search.');
        }

        $admin = $params['mode'] === 'admin';
        $target = $admin ? ($params['userid'] ?: null) : (int) $USER->id;
        if ($admin && !$params['chatid']) {
            throw new \invalid_parameter_exception('Administrative history search requires a context.');
        }
        $context = $params['chatid'] ? history_service::context($params['chatid'], $admin) : null;
        self::validate_context(\context_system::instance());
        $limit = history_service::configured_limit('history_search_limit');
        $records = history_repository::search($target, $params['chatid'] ?: null, $query, $limit);
        $usagebychat = [];
        foreach ($records as $record) {
            $usagebychat[$record->chatid][] = $record;
        }

        $contexts = [];
        foreach ($usagebychat as $resultchatid => $conversations) {
            $resultcontext = (int) $resultchatid === $params['chatid'] && $context
                ? $context : history_service::context((int) $resultchatid, false);
            $usage = history_repository::conversation_usage((int) $resultchatid, PHP_INT_MAX, $conversations);
            $items = [];
            foreach ($conversations as $conversation) {
                $conversationusage = $usage[$conversation->userid . '|' . $conversation->conversationkey] ?? null;
                $items[] = self::conversation($conversation, $conversationusage);
            }
            $contexts[] = [
                'chatid' => (int) $resultchatid,
                'name' => $resultcontext ? $resultcontext->get_context_name(false)
                    : get_string('historyunavailablecontext', 'local_parce'),
                'conversations' => $items,
            ];
        }
        if ($admin && $records) {
            $keysbyuser = [];
            foreach ($records as $record) {
                $keysbyuser[$record->userid][] = $record->conversationkey;
            }
            history_service::audit($context, array_column($records, 'userid'), $keysbyuser);
        }
        return ['contexts' => $contexts, 'limited' => count($records) >= $limit, 'resultlimit' => $limit];
    }

    /**
     * Format a minimal conversation search result.
     *
     * @param \stdClass $record The conversation record.
     * @param \stdClass|null $usage The usage information for the conversation.
     * @return array The formatted conversation data.
     */
    private static function conversation(\stdClass $record, ?\stdClass $usage): array {
        $guestid = (int) guest_user()->id;
        $user = (int) $record->userid === $guestid ? null
            : \core_user::get_user((int) $record->userid, '*', IGNORE_MISSING);
        return [
            'userid' => (int) $record->userid,
            'conversationkey' => $record->conversationkey,
            'lastactivity' => (int) $record->lastactivity,
            'turncount' => (int) $record->turncount,
            'prompttokens' => (int) ($usage->prompttokens ?? 0),
            'completiontokens' => (int) ($usage->completiontokens ?? 0),
            'displayname' => (int) $record->userid === $guestid ? get_string('historyguestsession', 'local_parce')
                : ($user ? fullname($user) : get_string('historyunavailableuser', 'local_parce')),
        ];
    }

    /**
     * Returns the structure of the search history results.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        $conversation = new external_single_structure([
            'userid' => new external_value(PARAM_INT, 'Conversation owner'),
            'conversationkey' => new external_value(PARAM_ALPHANUM, 'Conversation key'),
            'lastactivity' => new external_value(PARAM_INT, 'Last turn time'),
            'turncount' => new external_value(PARAM_INT, 'Matching turn count'),
            'prompttokens' => new external_value(PARAM_INT, 'Prompt tokens'),
            'completiontokens' => new external_value(PARAM_INT, 'Completion tokens'),
            'displayname' => new external_value(PARAM_TEXT, 'Safe owner label'),
        ]);
        return new external_single_structure([
            'contexts' => new external_multiple_structure(new external_single_structure([
                'chatid' => new external_value(PARAM_INT, 'Context ID'),
                'name' => new external_value(PARAM_TEXT, 'Safe context label'),
                'conversations' => new external_multiple_structure($conversation),
            ])),
            'limited' => new external_value(PARAM_BOOL, 'Whether the configured maximum was displayed'),
            'resultlimit' => new external_value(PARAM_INT, 'Configured result maximum'),
        ]);
    }
}
