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

namespace local_parce\local;

/**
 * Database queries for the history browser.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class history_repository {
    /**
     * List a user's chat contexts.
     *
     * @param int $userid The user ID.
     * @param int $snapshot The snapshot ID.
     * @param array|null $after The cursor for pagination.
     * @param int $limit The maximum number of chat contexts to return.
     * @return array The list of chat contexts.
     */
    public static function contexts(int $userid, int $snapshot, ?array $after, int $limit): array {
        global $DB;
        $params = ['userid' => $userid, 'snapshot' => $snapshot];
        $having = '';
        if ($after) {
            $having = ' HAVING MAX(timecreated) < :aftertime OR '
                . '(MAX(timecreated) = :aftertime2 AND chatid < :afterid)';
            $params += ['aftertime' => $after[0], 'aftertime2' => $after[0], 'afterid' => $after[1]];
        }
        $sql = "SELECT chatid, MAX(timecreated) AS lastactivity, COUNT(id) AS turncount
                  FROM {local_parce_conversation_entries}
                 WHERE userid = :userid AND id <= :snapshot
              GROUP BY chatid{$having}
              ORDER BY lastactivity DESC, chatid DESC";
        return array_values($DB->get_records_sql($sql, $params, 0, $limit + 1));
    }

    /**
     * List conversations in a context.
     *
     * @param int $chatid The chat ID.
     * @param int|null $userid The user ID, or null for all users.
     * @param int $snapshot The snapshot ID.
     * @param array|null $after The cursor for pagination.
     * @param int $limit The maximum number of conversations to return.
     * @param string|null $conversationkey The conversation key, or null for all keys.
     * @return array The list of conversations.
     */
    public static function conversations(
        int $chatid,
        ?int $userid,
        int $snapshot,
        ?array $after,
        int $limit,
        ?string $conversationkey = null
    ): array {
        global $DB;
        $params = ['chatid' => $chatid, 'snapshot' => $snapshot];
        $whereuser = '';
        if ($userid !== null) {
            $whereuser = ' AND e.userid = :userid';
            $params['userid'] = $userid;
        }
        $wherekey = '';
        if ($conversationkey !== null) {
            $wherekey = ' AND e.conversationkey = :conversationkey';
            $params['conversationkey'] = $conversationkey;
        }
        $having = '';
        if ($after) {
            $having = ' HAVING MAX(e.timecreated) < :at OR (MAX(e.timecreated) = :at2 AND '
                . '(e.userid < :au OR (e.userid = :au2 AND e.conversationkey < :ak)))';
            $params += ['at' => $after[0], 'at2' => $after[0], 'au' => $after[1],
                'au2' => $after[1], 'ak' => $after[2]];
        }
        $sql = "SELECT MIN(e.id) AS recordid, e.userid, e.conversationkey, MAX(e.timecreated) AS lastactivity,
                       COUNT(e.id) AS turncount
                  FROM {local_parce_conversation_entries} e
                 WHERE e.chatid = :chatid AND e.id <= :snapshot{$whereuser}{$wherekey}
              GROUP BY e.userid, e.conversationkey{$having}
              ORDER BY lastactivity DESC, e.userid DESC, e.conversationkey DESC";
        return array_values($DB->get_records_sql($sql, $params, 0, $limit + 1));
    }

    /**
     * Find conversation groups containing a complete phrase in visible questions or responses.
     *
     * @param int|null $userid The user ID to filter by, or null for all users.
     * @param int|null $chatid The chat ID to filter by, or null for all chats.
     * @param string $phrase The phrase to search for.
     * @param int $limit The maximum number of conversation groups to return.
     * @return array The list of conversation groups matching the search criteria.
     */
    public static function search(?int $userid, ?int $chatid, string $phrase, int $limit): array {
        global $DB;
        $like = '%' . $DB->sql_like_escape($phrase) . '%';
        $params = ['question' => $like, 'response' => $like];
        $whereuser = '';
        if ($userid !== null) {
            $whereuser = ' AND e.userid = :userid';
            $params['userid'] = $userid;
        }
        $wherechat = '';
        if ($chatid !== null) {
            $wherechat = ' AND e.chatid = :chatid';
            $params['chatid'] = $chatid;
        }
        $questionlike = $DB->sql_like('m.question', ':question', false);
        $responselike = $DB->sql_like('m.response', ':response', false);
        $sql = "SELECT MIN(e.id) AS recordid, e.chatid, e.userid, e.conversationkey,
                       MAX(e.timecreated) AS lastactivity, COUNT(e.id) AS turncount
                  FROM {local_parce_conversation_entries} e
                 WHERE EXISTS (
                           SELECT 1
                             FROM {local_parce_conversation_entries} m
                            WHERE m.userid = e.userid AND m.chatid = e.chatid
                                  AND m.conversationkey = e.conversationkey
                                  AND ({$questionlike} OR {$responselike})
                       ){$whereuser}{$wherechat}
              GROUP BY e.chatid, e.userid, e.conversationkey
              ORDER BY lastactivity DESC, e.chatid DESC, e.conversationkey DESC";
        return array_values($DB->get_records_sql($sql, $params, 0, $limit));
    }

    /**
     * Return token usage for only the conversation groups on the current page.
     *
     * @param int $chatid The chat ID.
     * @param int $snapshot The snapshot ID.
     * @param array $conversations The list of conversation groups on the current page.
     * @return array The token usage for each conversation group.
     */
    public static function conversation_usage(int $chatid, int $snapshot, array $conversations): array {
        global $DB;
        if (!$conversations) {
            return [];
        }
        $params = ['chatid' => $chatid, 'snapshot' => $snapshot];
        $scopes = [];
        foreach ($conversations as $index => $conversation) {
            $scopes[] = "(e.userid = :userid{$index} AND e.conversationkey = :key{$index})";
            $params["userid{$index}"] = (int) $conversation->userid;
            $params["key{$index}"] = $conversation->conversationkey;
        }
        $sql = "SELECT MIN(a.id) AS recordid, e.userid, e.conversationkey,
                       COALESCE(SUM(a.prompttokens), 0) AS prompttokens,
                       COALESCE(SUM(a.completiontokens), 0) AS completiontokens
                  FROM {local_parce_conversation_entries} e
                  JOIN {local_parce_ai_actions} a ON a.conversationentryid = e.id
                 WHERE e.chatid = :chatid AND e.id <= :snapshot AND (" . implode(' OR ', $scopes) . ")
              GROUP BY e.userid, e.conversationkey";
        $usage = [];
        foreach ($DB->get_records_sql($sql, $params) as $record) {
            $usage[$record->userid . '|' . $record->conversationkey] = $record;
        }
        return $usage;
    }

    /**
     * List chronological turns, always constrained by all three ownership keys.
     *
     * @param int $userid The user ID.
     * @param int $chatid The chat ID.
     * @param string $key The conversation key.
     * @param int $snapshot The snapshot ID.
     * @param array|null $after The cursor for pagination.
     * @param int $limit The maximum number of turns to return.
     * @return array The list of turns.
     */
    public static function turns(int $userid, int $chatid, string $key, int $snapshot, ?array $after, int $limit): array {
        global $DB;
        $params = ['userid' => $userid, 'chatid' => $chatid, 'key' => $key, 'snapshot' => $snapshot];
        $afterwhere = '';
        if ($after) {
            $afterwhere = ' AND (e.timecreated > :at OR (e.timecreated = :at2 AND e.id > :aid))';
            $params += ['at' => $after[0], 'at2' => $after[0], 'aid' => $after[1]];
        }
        $sql = "SELECT e.id, e.question, e.response, e.timecreated
                  FROM {local_parce_conversation_entries} e
                 WHERE e.userid = :userid AND e.chatid = :chatid AND e.conversationkey = :key
                       AND e.id <= :snapshot{$afterwhere}
              ORDER BY e.timecreated ASC, e.id ASC";
        return array_values($DB->get_records_sql($sql, $params, 0, $limit + 1));
    }

    /**
     * Return token usage for a bounded page of entry IDs.
     *
     * @param array $turns The list of turns to calculate usage for.
     * @return array The token usage for each turn.
     */
    public static function turn_usage(array $turns): array {
        global $DB;
        if (!$turns) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_map(fn($turn) => (int) $turn->id, $turns), SQL_PARAMS_NAMED);
        $sql = "SELECT conversationentryid AS id, COALESCE(SUM(prompttokens), 0) AS prompttokens,
                       COALESCE(SUM(completiontokens), 0) AS completiontokens
                  FROM {local_parce_ai_actions}
                 WHERE conversationentryid {$insql}
              GROUP BY conversationentryid";
        return $DB->get_records_sql($sql, $params);
    }
}
