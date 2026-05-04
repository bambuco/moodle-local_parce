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
 * Class controller
 *
 * Handles chat component visibility and conversation cache management.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class controller
{
    /**
     * Check if the chat component should be included on the page.
     *
     * @return bool True if the chat should be included, false otherwise
     */
    public static function chat_include(): bool {
        global $COURSE;

        static $included = null;
        if ($included !== null) {
            return $included;
        }

        $included = false;
        // Check if the plugin is enabled. Default to enabled if not configured.
        $enabled = get_config('local_parce', 'enabled');
        if (!$enabled) {
            return false;
        }

        // Check user permissions.
        $allowguests = get_config('local_parce', 'enable_guests');
        if (!isloggedin() || (isguestuser() && !$allowguests)) {
            return false;
        }

        // Check capability.
        if ($COURSE->id != SITEID) {
            $context = \context_course::instance($COURSE->id);
        } else {
            $context = \context_system::instance();
        }

        if (!has_capability('local/parce:usechat', $context)) {
            return false;
        }

        $included = true;
        return true;
    }

    /**
     * Get the conversation cache instance.
     *
     * @return \cache The cache instance for conversations
     */
    private static function get_cache(): \cache {
        return \cache::make('local_parce', 'conversation');
    }

    /**
     * Get the cache configuration settings.
     *
     * Returns an associative array with 'ttl' and 'maxentries' keys.
     *
     * @return array Configuration settings with default values
     */
    private static function get_cache_config(): array {
        $ttl = get_config('local_parce', 'cache_ttl');
        $maxentries = get_config('local_parce', 'cache_maxentries');

        // ToDo: Use ttl in cache definition and implement cache cleanup based on ttl.
        // Currently, ttl is not enforced in the cache implementation.
        return [
            'ttl' => $ttl !== false ? (int)$ttl : 3600,
            'maxentries' => $maxentries !== false ? (int)$maxentries : 50,
        ];
    }

    /**
     * Generate a unique cache key for a conversation.
     *
     * @param int $userid The user ID
     * @param int $chatid The chat ID
     * @return string The unique cache key
     */
    private static function get_cache_key(int $userid, int $chatid): string {
        return "conversation_{$userid}_{$chatid}";
    }

    /**
     * Generate a unique conversation identifier.
     *
     * Creates a unique identifier based on userid, chatid, and session.
     * This ensures that each conversation is uniquely identified within a session.
     * When the session is renewed, a new identifier is generated.
     *
     * @param int $userid The user ID
     * @param int $chatid The chat ID
     * @return string The unique conversation identifier (SHA256 hash)
     */
    public static function generate_conversation_key(int $userid, int $chatid): string {
        $sessionid = session_id();
        return hash('sha256', "{$userid}_{$chatid}_{$sessionid}");
    }

    /**
     * Register a chatid for a specific user in the index.
     *
     * Maintains an index of all chatids used by a user to enable
     * efficient deletion of only that user's conversations without
     * affecting other users' cache data.
     *
     * @param int $userid The user ID
     * @param int $chatid The chat ID to register
     * @return void
     */
    private static function register_user_chatid(int $userid, int $chatid): void {
        $cache = self::get_cache();
        $indexkey = "user_chatids_{$userid}";

        // Get the existing index for this user.
        $chatids = $cache->get($indexkey);
        if ($chatids === false) {
            $chatids = [];
        }

        // Add chatid if not already in the index.
        if (!in_array($chatid, $chatids)) {
            $chatids[] = $chatid;
            $cache->set($indexkey, $chatids);
        }
    }

    /**
     * Store a conversation entry in both cache and database.
     *
     * Persists a conversation entry (question and response) in the session cache
     * for quick retrieval and in the database for permanent storage and audit trail.
     *
     * @param int $userid The user ID
     * @param int $chatid The chat ID
     * @param string $question The user's question
     * @param string $response The system's response
     * @return int The ID of the inserted conversation entry record
     */
    public static function store_conversation_entry(int $userid, int $chatid, string $question, string $response): int {
        global $DB;

        $cache = self::get_cache();
        $config = self::get_cache_config();
        $key = self::get_cache_key($userid, $chatid);
        $conversationkey = self::generate_conversation_key($userid, $chatid);
        $currenttime = time();

        // Register this chatid in the user's index for later cleanup
        self::register_user_chatid($userid, $chatid);

        $data = $cache->get($key);
        if ($data === false) {
            $data = [
                'entries' => [],
                'created' => $currenttime,
                'conversationkey' => $conversationkey,
            ];
        }

        // Add user question.
        $data['entries'][] = [
            'role' => 'user',
            'content' => $question,
            'timestamp' => $currenttime,
        ];

        // Add system response.
        $data['entries'][] = [
            'role' => 'system',
            'content' => $response,
            'timestamp' => $currenttime,
        ];

        // Enforce maximum entries limit by removing oldest entries.
        $entrycount = count($data['entries']);
        if ($entrycount > $config['maxentries']) {
            $data['entries'] = array_slice($data['entries'], -$config['maxentries']);
        }

        $data['lastaccess'] = $currenttime;

        // Store in cache
        $cache->set($key, $data);

        // Store in database
        $record = new \stdClass();
        $record->userid = $userid;
        $record->chatid = $chatid;
        $record->conversationkey = $conversationkey;
        $record->question = $question;
        $record->response = $response;
        $record->timecreated = $currenttime;

        return $DB->insert_record('local_parce_conversation_entries', $record);
    }

    /**
     * Store a conversation entry (question and response) in cache.
     *
     * @deprecated Use store_conversation_entry instead. This method is kept for backward compatibility.
     * @param int $userid The user ID
     * @param int $chatid The chat ID
     * @param string $question The user's question
     * @param string $response The system's response
     * @return int The ID of the inserted conversation entry record
     */
    public static function store_conversation(int $userid, int $chatid, string $question, string $response): int {
        return self::store_conversation_entry($userid, $chatid, $question, $response);
    }

    /**
     * Retrieve a conversation from cache.
     *
     * Returns the cached conversation data including all stored entries,
     * or null if the conversation is not cached.
     *
     * @param int $userid The user ID
     * @param int $chatid The chat ID
     * @return array|null The conversation data or null if not found
     */
    public static function get_conversation(int $userid, int $chatid): ?array {
        $cache = self::get_cache();
        $key = self::get_cache_key($userid, $chatid);

        $data = $cache->get($key);
        if ($data === false) {
            return null;
        }

        return $data;
    }

    /**
     * Get all entries from a cached conversation.
     *
     * Returns an array of conversation entries with role and content.
     *
     * @param int $userid The user ID
     * @param int $chatid The chat ID
     * @return array Empty array if conversation not found, otherwise array of entries
     */
    public static function get_conversation_entries(int $userid, int $chatid): array {
        $conversation = self::get_conversation($userid, $chatid);
        if ($conversation === null) {
            return [];
        }

        return $conversation['entries'] ?? [];
    }

    /**
     * Clear a conversation from cache.
     *
     * @param int $userid The user ID
     * @param int $chatid The chat ID
     * @return void
     */
    public static function clear_conversation(int $userid, int $chatid): void {
        $cache = self::get_cache();
        $key = self::get_cache_key($userid, $chatid);
        $cache->delete($key);
    }

    /**
     * Clear all conversations for a specific user.
     *
     * Deletes only the conversations belonging to the specified user,
     * without affecting other users' cached conversation data.
     *
     * @param int $userid The user ID
     * @return void
     */
    public static function clear_user_conversations(int $userid): void {
        $cache = self::get_cache();
        $indexkey = "user_chatids_{$userid}";

        // Get the list of chatids for this user.
        $chatids = $cache->get($indexkey);

        if ($chatids !== false && is_array($chatids)) {
            // Delete each conversation individually (not affecting other users).
            foreach ($chatids as $chatid) {
                $key = self::get_cache_key($userid, $chatid);
                $cache->delete($key);
            }
            // Delete the user's index.
            $cache->delete($indexkey);
        }
    }

    /**
     * Get conversation entries with pagination support.
     *
     * Returns a paginated set of conversation entries, ordered from newest to oldest.
     * If limit is 0, returns all entries.
     *
     * @param int $userid The user ID
     * @param int $chatid The chat ID (can be course ID or other context identifier)
     * @param int $offset Number of entries to skip from the end (newest first)
     * @param int $limit Number of entries to return. 0 = all entries
     * @return array Array with 'entries', 'total', 'offset', 'limit', 'hasmore' keys
     */
    public static function get_conversation_entries_paginated(int $userid, int $chatid, int $offset = 0, int $limit = 20): array {
        $allentries = self::get_conversation_entries($userid, $chatid);
        $total = count($allentries);

        // Reverse to get newest first, apply offset and limit.
        $reversed = array_reverse($allentries, true);
        $paginated = ($limit > 0) ? array_slice($reversed, $offset, $limit) : $reversed;

        // Re-reverse to maintain original chronological order in response.
        $entries = array_reverse($paginated, true);

        return [
            'entries' => array_values($entries),
            'total' => $total,
            'offset' => $offset,
            'limit' => $limit,
            'hasmore' => ($offset + count($entries)) < $total,
        ];
    }

    /**
     * Format a timestamp for display in chat UI.
     *
     * Returns a human-readable timestamp format appropriate for the chat interface.
     *
     * @param int $timestamp Unix timestamp to format
     * @return string Formatted timestamp for display (e.g., "14:30", "Today 14:30", "15 Jan")
     */
    public static function format_timestamp(int $timestamp): string {
        $timestamp = $timestamp ?? time();

        $strftimetime = get_string('strftimetime24', 'langconfig');
        // Same day - show only time.
        if (date('Y-m-d') === date('Y-m-d', $timestamp)) {
            return userdate($timestamp, $strftimetime);
        }

        // Yesterday.
        if (date('Y-m-d', strtotime('-1 day')) === date('Y-m-d', $timestamp)) {
            $yesterday = get_string('yesterday', 'local_parce', userdate($timestamp, $strftimetime));
            return $yesterday;
        }

        // Same year - show month and day.
        if (date('Y') === date('Y', $timestamp)) {
            return userdate($timestamp, get_string('strftimedateshortmonthabbr', 'langconfig'));
        }

        // Different year - show full date.
        return userdate($timestamp, get_string('strftimedatemonthabbr', 'langconfig'));
    }

    /**
     * Log an AI action transaction to the database.
     *
     * @param int $userid The user ID
     * @param int $contextid The Moodle context ID
     * @param int $chatid The chat context identifier
     * @param string $conversationkey The conversation session key
     * @param string $actiontype The type of AI call (question_plan or answer_question)
     * @param string $prompt The system instruction prompt
     * @param string $prompttext The full hackquestion text sent to the AI
     * @param \core_ai\aiactions\responses\response_base $response The AI response object
     * @return int The ID of the inserted ai_actions record
     */
    public static function log_ai_action(
        int $userid,
        int $contextid,
        int $chatid,
        string $conversationkey,
        string $actiontype,
        string $prompt,
        string $prompttext,
        \core_ai\aiactions\responses\response_base $response
    ): int {
        global $DB;

        $now = time();
        $record = new \stdClass();
        $record->userid = $userid;
        $record->contextid = $contextid;
        $record->chatid = $chatid;
        $record->conversationkey = $conversationkey;
        $record->actiontype = $actiontype;
        $record->prompt = $prompt;
        $record->prompttext = $prompttext;
        $record->success = $response->get_success() ? 1 : 0;
        $record->timecreated = $now;

        if ($response->get_success()) {
            $data = $response->get_response_data();
            $record->generatedcontent = $data['generatedcontent'] ?? null;
            $record->responseid = $data['id'] ?? null;
            $record->fingerprint = $data['fingerprint'] ?? null;
            $record->finishreason = $data['finishreason'] ?? null;
            $record->prompttokens = $data['prompttokens'] ?? null;
            $record->completiontokens = $data['completiontokens'] ?? null;
            $record->model = $data['model'] ?? null;
            $record->timecompleted = $now;
        } else {
            $record->errorcode = $response->get_errorcode();
            $record->errormessage = $response->get_errormessage();
        }

        return $DB->insert_record('local_parce_ai_actions', $record);
    }

    /**
     * Update an AI action record with intent information and optionally the conversation entry ID.
     *
     * @param int $actionid The ID of the ai_actions record to update
     * @param string $intent The detected intent type
     * @param array|null $intentparams The intent parameters (will be JSON-encoded)
     * @param int|null $conversationentryid The conversation entry ID to link
     * @return void
     */
    public static function update_ai_action(int $actionid, string $intent, ?array $intentparams = null, ?int $conversationentryid = null): void {
        global $DB;

        $update = new \stdClass();
        $update->id = $actionid;
        $update->intent = $intent;

        if ($intentparams !== null) {
            $update->intentparams = json_encode($intentparams);
        }

        if ($conversationentryid !== null) {
            $update->conversationentryid = $conversationentryid;
        }

        $DB->update_record('local_parce_ai_actions', $update);
    }
}
