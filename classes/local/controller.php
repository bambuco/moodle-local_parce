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
    /** Maximum length accepted for a question. */
    public const MAX_QUESTION_LENGTH = 4000;

    /** Maximum number of turns returned by a history request. */
    public const MAX_HISTORY_LIMIT = 100;

    /** Maximum number of complete recent turns included in an AI prompt. */
    public const MAX_PROMPT_TURNS = 8;

    /** Maximum estimated tokens from recent turns included in an AI prompt. */
    public const MAX_PROMPT_TOKENS = 8000;

    /** Maximum estimated tokens of retrieved Search or Calendar content. */
    public const MAX_RETRIEVED_TOKENS = 8000;

    /** Maximum estimated tokens in an entire provider request. */
    public const MAX_PAYLOAD_TOKENS = 18000;

    /** Hard maximum number of complete active turns. */
    public const MAX_ACTIVE_TURNS = 40;

    /** Number of Unicode characters used for conservative token estimation. */
    private const CHARACTERS_PER_TOKEN = 3;

    /**
     * Resolve the canonical context used to identify a chat.
     *
     * Chats belong to the containing course context. Pages outside a course
     * use the system context.
     *
     * @param \context $context Current Moodle context
     * @return \context_course|\context_system
     */
    public static function get_chat_context(\context $context): \context {
        $coursecontext = $context->get_course_context(false);
        if ($coursecontext) {
            return $coursecontext;
        }

        return \context_system::instance();
    }

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
        if (!isloggedin()) {
            return false;
        }

        // Check capability.
        if ($COURSE->id != SITEID) {
            $context = \context_course::instance($COURSE->id);
        } else {
            $context = \context_system::instance();
        }

        $included = self::can_use_chat($context);
        return $included;
    }

    /**
     * Check server-side access to the chat.
     *
     * @param \context $context Request context
     * @return bool
     */
    public static function can_use_chat(\context $context): bool {
        if (!get_config('local_parce', 'enabled')) {
            return false;
        }

        if (isguestuser()) {
            return (bool) get_config('local_parce', 'enable_guests');
        }

        return has_capability('local/parce:usechat', $context);
    }

    /**
     * Require server-side access to the chat.
     *
     * @param \context $context Request context
     */
    public static function require_chat_access(\context $context): void {
        if (!self::can_use_chat($context)) {
            throw new \required_capability_exception($context, 'local/parce:usechat', 'nopermissions', '');
        }
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
     * Get and validate the active-conversation limits.
     *
     * Invalid stored values are rejected rather than silently clamped. The same
     * hard bounds are enforced by the admin settings form.
     *
     * @return array Configuration settings with validated values
     */
    private static function get_cache_config(): array {
        $maxentries = get_config('local_parce', 'cache_maxentries');
        $maxtokens = get_config('local_parce', 'conversation_maxtokens');
        $maxentries = $maxentries === false ? self::MAX_ACTIVE_TURNS : (int) $maxentries;
        $maxtokens = $maxtokens === false ? 16000 : (int) $maxtokens;

        if ($maxentries < 1 || $maxentries > self::MAX_ACTIVE_TURNS) {
            throw new \coding_exception('local_parce/cache_maxentries must be between 1 and 40 complete turns.');
        }
        if ($maxtokens < 1 || $maxtokens > 16000) {
            throw new \coding_exception('local_parce/conversation_maxtokens must be between 1 and 16000.');
        }

        return [
            'maxentries' => $maxentries,
            'maxtokens' => $maxtokens,
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
        $sessionkey = hash('sha256', session_id());
        return "conversation_{$userid}_{$chatid}_{$sessionkey}";
    }

    /**
     * Return the durable, non-personal active-cache invalidation token.
     *
     * @return int
     */
    public static function get_active_cache_version(): int {
        $version = get_config('local_parce', 'activecacheversion');
        return $version === false ? 1 : max(1, (int) $version);
    }

    /**
     * Invalidate active conversations in every PHP session.
     */
    public static function invalidate_active_conversations(): void {
        set_config('activecacheversion', self::get_active_cache_version() + 1, 'local_parce');
    }

    /**
     * Check whether a snapshot taken before an AI request is still current.
     *
     * @param int $version Cache version captured before the request
     * @return bool
     */
    public static function is_active_cache_version(int $version): bool {
        return $version === self::get_active_cache_version();
    }

    /**
     * Discard stale session data and return current data or false.
     *
     * @param \cache $cache Conversation cache
     * @param string $key Session-specific cache key
     * @return array|false
     */
    private static function get_current_cache_data(\cache $cache, string $key): array|false {
        $data = $cache->get($key);
        if ($data !== false && ($data['cacheversion'] ?? 0) !== self::get_active_cache_version()) {
            $cache->delete($key);
            return false;
        }
        return $data;
    }

    /**
     * Estimate tokens consistently before an AI provider request.
     *
     * Moodle AI providers report token usage only after a request. A conservative
     * language-independent approximation is therefore used for conversation
     * limits and prompt selection.
     *
     * @param string $content Message content, which may contain HTML
     * @return int Estimated token count
     */
    public static function estimate_tokens(string $content): int {
        $content = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $length = \core_text::strlen(trim($content));
        if ($length === 0) {
            return 0;
        }

        return (int) ceil($length / self::CHARACTERS_PER_TOKEN);
    }

    /**
     * Estimate tokens in the exact serialized payload sent to the provider.
     *
     * @param string $content Serialized provider payload
     * @return int
     */
    public static function estimate_payload_tokens(string $content): int {
        $length = \core_text::strlen($content);
        return $length === 0 ? 0 : (int) ceil($length / self::CHARACTERS_PER_TOKEN);
    }

    /**
     * Calculate token and turn usage for an active conversation.
     *
     * @param int $userid User ID
     * @param int $chatid Canonical chat context ID
     * @return array Usage values for the UI and rollover checks
     */
    public static function get_conversation_usage(int $userid, int $chatid): array {
        $entries = self::get_conversation_entries($userid, $chatid);
        $tokens = 0;
        foreach ($entries as $entry) {
            $tokens += self::estimate_tokens($entry['content'] ?? '');
        }

        $config = self::get_cache_config();
        $turns = (int) floor(count($entries) / 2);
        $tokenpercentage = (int) floor(($tokens * 100) / $config['maxtokens']);
        $turnpercentage = (int) floor(($turns * 100) / $config['maxentries']);
        return [
            'tokens' => $tokens,
            'limit' => $config['maxtokens'],
            'percentage' => min(100, max($tokenpercentage, $turnpercentage)),
            'turns' => $turns,
            'turnlimit' => $config['maxentries'],
        ];
    }

    /**
     * Start a new conversation before a question would reach a configured limit.
     *
     * @param int $userid User ID
     * @param int $chatid Canonical chat context ID
     * @param string $question Question about to be processed
     * @param array|null $snapshot Receives the state required to roll back a failed question
     * @return bool Whether a previous active conversation was rotated
     */
    public static function prepare_conversation(
        int $userid,
        int $chatid,
        string $question,
        ?array &$snapshot = null
    ): bool {
        $cache = self::get_cache();
        $cachekey = self::get_cache_key($userid, $chatid);
        $data = self::get_current_cache_data($cache, $cachekey);
        $snapshot = [
            'cachekey' => $cachekey,
            'data' => $data,
            'cacheversion' => self::get_active_cache_version(),
        ];
        $rotated = false;

        if ($data !== false) {
            $usage = self::get_conversation_usage($userid, $chatid);
            $projectedtokens = $usage['tokens'] + self::estimate_tokens($question);
            if ($usage['turns'] >= $usage['turnlimit'] || $projectedtokens >= $usage['limit']) {
                $cache->delete($cachekey);
                $rotated = true;
            }
        }

        self::generate_conversation_key($userid, $chatid);
        return $rotated;
    }

    /**
     * Restore the active conversation state captured before a failed question.
     *
     * Privacy invalidation always wins over restoration of an older snapshot.
     *
     * @param array|null $snapshot Snapshot returned by prepare_conversation()
     * @return void
     */
    public static function restore_prepared_conversation(?array $snapshot): void {
        if (
            $snapshot === null
            || !isset($snapshot['cachekey'], $snapshot['cacheversion'])
            || !self::is_active_cache_version((int)$snapshot['cacheversion'])
        ) {
            return;
        }

        $cache = self::get_cache();
        if (($snapshot['data'] ?? false) === false) {
            $cache->delete($snapshot['cachekey']);
            return;
        }

        $cache->set($snapshot['cachekey'], $snapshot['data']);
    }

    /**
     * Generate a unique conversation identifier.
     *
     * Returns the identifier stored with the active cached conversation. A new
     * identifier is created for a new session or after the turn limit is reached.
     *
     * @param int $userid The user ID
     * @param int $chatid The chat ID
     * @return string The cryptographically random conversation identifier
     */
    public static function generate_conversation_key(int $userid, int $chatid): string {
        $cache = self::get_cache();
        $cachekey = self::get_cache_key($userid, $chatid);
        $data = self::get_current_cache_data($cache, $cachekey);
        $config = self::get_cache_config();
        $turncount = $data === false ? 0 : (int) floor(count($data['entries'] ?? []) / 2);

        $tokencount = 0;
        foreach ($data['entries'] ?? [] as $entry) {
            $tokencount += self::estimate_tokens($entry['content'] ?? '');
        }

        if ($data !== false && ($turncount >= $config['maxentries'] || $tokencount >= $config['maxtokens'])) {
            $cache->delete($cachekey);
            $data = false;
        }

        if ($data === false) {
            // Keep the durable identifier independent from PHP session identifiers.
            // Session isolation belongs exclusively to the internal cache key.
            $conversationkey = bin2hex(random_bytes(32));
            $data = [
                'entries' => [],
                'created' => time(),
                'conversationkey' => $conversationkey,
                'cacheversion' => self::get_active_cache_version(),
            ];
            $cache->set($cachekey, $data);
            self::register_user_cache_key($userid, $cachekey);
        }

        return $data['conversationkey'];
    }

    /**
     * Register a session-specific conversation cache key for a user.
     *
     * Maintains an index of all cache keys used by a user to enable
     * efficient deletion of only that user's conversations without
     * affecting other users' cache data.
     *
     * @param int $userid The user ID
     * @param string $cachekey Cache key to register
     * @return void
     */
    private static function register_user_cache_key(int $userid, string $cachekey): void {
        $cache = self::get_cache();
        $indexkey = "user_cachekeys_{$userid}";

        // Get the existing index for this user.
        $cachekeys = $cache->get($indexkey);
        if ($cachekeys === false) {
            $cachekeys = [];
        }

        // Add the key if not already in the index.
        if (!in_array($cachekey, $cachekeys, true)) {
            $cachekeys[] = $cachekey;
            $cache->set($indexkey, $cachekeys);
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
     * @param int[] $actionids AI action records to link atomically
     * @param int|null $cacheversion Expected global cache version
     * @return int The ID of the inserted conversation entry record
     */
    public static function store_conversation_entry(
        int $userid,
        int $chatid,
        string $question,
        string $response,
        array $actionids = [],
        ?int $cacheversion = null,
        bool $persist = true
    ): int {
        global $DB;

        if ($cacheversion !== null && !self::is_active_cache_version($cacheversion)) {
            return 0;
        }

        $cache = self::get_cache();
        $key = self::get_cache_key($userid, $chatid);
        $conversationkey = self::generate_conversation_key($userid, $chatid);
        $currenttime = time();

        $data = self::get_current_cache_data($cache, $key);
        if ($data === false) {
            $data = [
                'entries' => [],
                'created' => $currenttime,
                'conversationkey' => $conversationkey,
                'cacheversion' => self::get_active_cache_version(),
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

        $data['lastaccess'] = $currenttime;

        if ($cacheversion !== null && !self::is_active_cache_version($cacheversion)) {
            return 0;
        }

        $entryid = 0;
        if ($persist) {
            // Store the durable turn and its action links as one database operation.
            $transaction = $DB->start_delegated_transaction();
            $record = new \stdClass();
            $record->userid = $userid;
            $record->chatid = $chatid;
            $record->conversationkey = $conversationkey;
            $record->question = $question;
            $record->response = $response;
            $record->timecreated = $currenttime;
            $entryid = $DB->insert_record('local_parce_conversation_entries', $record);
            foreach ($actionids as $actionid) {
                $DB->set_field('local_parce_ai_actions', 'conversationentryid', $entryid, ['id' => $actionid]);
            }
            $transaction->allow_commit();
        }

        // Only expose a completed, durably persisted turn in the active cache.
        if ($cacheversion === null || self::is_active_cache_version($cacheversion)) {
            $cache->set($key, $data);
        }
        return $entryid;
    }

    /**
     * Store a conversation entry (question and response) in cache.
     *
     * @deprecated Use store_conversation_entry instead. This method is kept for backward compatibility.
     * @param int $userid The user ID
     * @param int $chatid The chat ID
     * @param string $question The user's question
     * @param string $response The system's response
     * @param int[] $actionids AI action records to link atomically
     * @return int The ID of the inserted conversation entry record
     */
    public static function store_conversation(
        int $userid,
        int $chatid,
        string $question,
        string $response,
        array $actionids = [],
        ?int $cacheversion = null,
        bool $persist = true
    ): int {
        return self::store_conversation_entry(
            $userid,
            $chatid,
            $question,
            $response,
            $actionids,
            $cacheversion,
            $persist
        );
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

        $data = self::get_current_cache_data($cache, $key);
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
     * Select recent complete turns from active cache for the AI prompt.
     *
     * Persistent history is deliberately never read here. Incomplete or malformed
     * cached entries are also excluded.
     *
     * @param int $userid User ID
     * @param int $chatid Canonical chat context ID
     * @return array Chronological role/content messages
     */
    public static function get_prompt_context(int $userid, int $chatid): array {
        $entries = self::get_conversation_entries($userid, $chatid);
        $turns = [];
        for ($index = 0; $index + 1 < count($entries); $index += 2) {
            $question = $entries[$index];
            $answer = $entries[$index + 1];
            if (($question['role'] ?? '') !== 'user' || ($answer['role'] ?? '') !== 'system') {
                continue;
            }
            $turns[] = [$question, $answer];
        }

        $selected = [];
        $tokens = 0;
        foreach (array_reverse($turns) as $turn) {
            $turntokens = self::estimate_tokens($turn[0]['content']) + self::estimate_tokens($turn[1]['content']);
            if (count($selected) >= self::MAX_PROMPT_TURNS || $tokens + $turntokens > self::MAX_PROMPT_TOKENS) {
                break;
            }
            $selected[] = $turn;
            $tokens += $turntokens;
        }

        $context = [];
        foreach (array_reverse($selected) as $turn) {
            foreach ($turn as $entry) {
                $context[] = [
                    'role' => $entry['role'],
                    'content' => $entry['content'],
                ];
            }
        }

        return $context;
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
        $indexkey = "user_cachekeys_{$userid}";

        // Get the list of session-specific keys for this user.
        $cachekeys = $cache->get($indexkey);

        if ($cachekeys !== false && is_array($cachekeys)) {
            // Delete each conversation individually (not affecting other users).
            foreach ($cachekeys as $cachekey) {
                $cache->delete($cachekey);
            }
            // Delete the user's index.
            $cache->delete($indexkey);
        }

        // Remove cache records indexed by versions prior to session isolation.
        $legacyindexkey = "user_chatids_{$userid}";
        $legacychatids = $cache->get($legacyindexkey);
        if ($legacychatids !== false && is_array($legacychatids)) {
            foreach ($legacychatids as $chatid) {
                $cache->delete("conversation_{$userid}_{$chatid}");
            }
            $cache->delete($legacyindexkey);
        }
    }

    /**
     * Clear every active conversation from cache.
     *
     * Used when a context-wide privacy deletion cannot enumerate all session
     * cache keys for that context.
     */
    public static function clear_all_conversations(): void {
        self::get_cache()->purge();
    }

    /**
     * Encode retrieved items as valid JSON within a token budget.
     *
     * Retrieved Search and Calendar values are untrusted data, never instructions.
     * Content is Unicode-safely shortened first; whole trailing items are then
     * discarded if structural data alone exceeds the budget.
     */
    public static function encode_retrieved_items(array $items, int $tokenbudget = self::MAX_RETRIEVED_TOKENS): string {
        $items = array_values($items);
        while ($items) {
            $json = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($json !== false && self::estimate_payload_tokens($json) <= $tokenbudget) {
                return $json;
            }
            $changed = false;
            foreach ($items as &$item) {
                if (is_object($item)) {
                    $item = (array) $item;
                }
                if (
                    is_array($item) && isset($item['content']) && is_string($item['content'])
                    && self::estimate_tokens($item['content']) > 1
                ) {
                    $length = \core_text::strlen($item['content']);
                    $item['content'] = \core_text::substr($item['content'], 0, max(0, (int) floor($length / 2)));
                    $changed = true;
                }
            }
            unset($item);
            if (!$changed) {
                array_pop($items);
            }
        }
        return '';
    }

    /**
     * Build a delimited provider payload which never exceeds the total budget.
     *
     * The markers separate instructions, user text, prior turns and untrusted
     * retrieved content inside one text prompt. They do not claim or emulate a
     * provider-native system role.
     */
    public static function build_ai_payload(
        string $instruction,
        string $question,
        array $previous = [],
        string $content = ''
    ): string {
        do {
            $payload = '<INSTRUCTION_START>' . $instruction . '<INSTRUCTION_END>'
                . '<QUESTION_START>' . $question . '<QUESTION_END>';
            if ($previous) {
                $text = '';
                foreach ($previous as $entry) {
                    $text .= '<ROLE_START>' . ($entry['role'] ?? '') . '<ROLE_END>'
                        . '<MESSAGE_START>' . ($entry['content'] ?? '') . '<MESSAGE_END>';
                }
                $payload .= '<PREVIOUS_START>' . $text . '<PREVIOUS_END>';
            }
            if ($content !== '') {
                $payload .= '<CONTENT_START>' . $content . '<CONTENT_END>';
            }
            if (self::estimate_payload_tokens($payload) <= self::MAX_PAYLOAD_TOKENS) {
                return $payload;
            }
            if ($previous) {
                array_splice($previous, 0, min(2, count($previous)));
                continue;
            }
            if ($content !== '') {
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    $content = self::encode_retrieved_items(
                        $decoded,
                        max(
                            1,
                            self::MAX_PAYLOAD_TOKENS
                            - self::estimate_payload_tokens($payload)
                            + self::estimate_payload_tokens($content)
                        )
                    );
                } else {
                    $content = \core_text::substr($content, 0, (int) floor(\core_text::strlen($content) / 2));
                }
                continue;
            }
            return \core_text::substr($payload, 0, self::MAX_PAYLOAD_TOKENS * self::CHARACTERS_PER_TOKEN);
        } while (true);
    }

    /**
     * Get persistent conversation history from the database.
     *
     * Pagination is expressed in complete turns so a question and its response
     * are never split across pages.
     *
     * @param int $userid User whose history is requested
     * @param int $chatid Canonical chat context ID
     * @param int $offset Number of newest turns to skip
     * @param int $limit Maximum number of turns to return
     * @return array History entries and pagination metadata
     */
    public static function get_history_entries_paginated(
        int $userid,
        int $chatid,
        int $offset = 0,
        int $limit = 20
    ): array {
        global $DB;

        $conditions = ['userid' => $userid, 'chatid' => $chatid];
        $total = $DB->count_records('local_parce_conversation_entries', $conditions);
        $records = $DB->get_records(
            'local_parce_conversation_entries',
            $conditions,
            'timecreated DESC, id DESC',
            'id, conversationkey, question, response, timecreated',
            $offset,
            $limit
        );

        $entries = [];
        foreach (array_reverse($records) as $record) {
            $entries[] = [
                'role' => 'user',
                'content' => $record->question,
                'timestamp' => $record->timecreated,
                'conversationkey' => $record->conversationkey,
            ];
            $entries[] = [
                'role' => 'system',
                'content' => clean_text($record->response, FORMAT_HTML),
                'timestamp' => $record->timecreated,
                'conversationkey' => $record->conversationkey,
            ];
        }

        return [
            'entries' => $entries,
            'total' => $total,
            'offset' => $offset,
            'limit' => $limit,
            'hasmore' => ($offset + count($records)) < $total,
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
     * Open an AI call trace before provider resolution.
     *
     * @return int New trace ID
     */
    public static function start_ai_action(
        int $userid,
        int $contextid,
        int $chatid,
        string $conversationkey,
        string $requestid,
        string $callid,
        string $actiontype,
        string $prompt = '',
        string $prompttext = ''
    ): int {
        global $DB;

        return $DB->insert_record('local_parce_ai_actions', (object) [
            'userid' => $userid,
            'contextid' => $contextid,
            'chatid' => $chatid,
            'conversationkey' => $conversationkey,
            'requestid' => $requestid,
            'callid' => $callid,
            'attemptordinal' => 0,
            'actiontype' => $actiontype,
            'prompt' => $prompt,
            'prompttext' => $prompttext,
            'status' => 'started',
            'success' => 0,
            'timecreated' => time(),
        ]);
    }

    /**
     * Close the initial trace and append any fallback attempts.
     *
     * @param int $actionid Initial trace ID
     * @param string $outcome Stable completion reason
     * @param array $generation Gateway metadata
     * @param \core_ai\aiactions\responses\response_base|null $response Provider response
     * @param string|null $errormessage Technical exception detail
     * @return int[] IDs for all attempts
     */
    public static function complete_ai_action(
        int $actionid,
        string $outcome,
        array $generation = [],
        ?\core_ai\aiactions\responses\response_base $response = null,
        ?string $errormessage = null
    ): array {
        global $DB;

        $base = $DB->get_record('local_parce_ai_actions', ['id' => $actionid], '*', MUST_EXIST);
        $attempts = $generation['attempts'] ?? [];
        if (!$attempts) {
            $attempts = [[
                'attemptordinal' => empty($generation['providerattempted']) ? 0 : 1,
                'durationms' => $generation['durationms'] ?? 0,
                'success' => $response?->get_success() ?? false,
                'errormessage' => $errormessage,
            ]];
        }

        $ids = [];
        foreach ($attempts as $index => $attempt) {
            $record = clone $base;
            unset($record->id);
            $record->attemptordinal = $attempt['attemptordinal'] ?? ($index + 1);
            $record->status = 'completed';
            $record->outcome = $index === array_key_last($attempts)
                ? $outcome
                : self::classify_attempt_outcome($attempt);
            $record->completionreason = $outcome;
            $record->success = !empty($attempt['success']) ? 1 : 0;
            $record->durationms = $attempt['durationms'] ?? ($generation['durationms'] ?? 0);
            $record->providercomponent = $attempt['providercomponent'] ?? null;
            $record->providerinstanceid = $attempt['providerinstanceid'] ?? null;
            $record->providername = $attempt['providername'] ?? null;
            $record->provider = isset($attempt['providercomponent'])
                ? $attempt['providercomponent'] . '#' . $attempt['providerinstanceid']
                : ($generation['provider'] ?? null);
            $record->model = $attempt['model'] ?? null;
            $record->prompttokens = $attempt['prompttokens'] ?? null;
            $record->completiontokens = $attempt['completiontokens'] ?? null;
            $record->finishreason = $attempt['finishreason'] ?? null;
            $record->errorcode = $attempt['errorcode'] ?? ($response?->get_errorcode());
            $record->errormessage = $attempt['errormessage'] ?? $errormessage ?? ($response?->get_errormessage());
            $record->timecompleted = time();
            if ($index === count($attempts) - 1 && $response?->get_success()) {
                $data = $response->get_response_data();
                $record->generatedcontent = $data['generatedcontent'] ?? null;
                $record->responseid = $data['id'] ?? null;
                $record->fingerprint = $data['fingerprint'] ?? null;
            }
            if ($index === 0) {
                $record->id = $actionid;
                $DB->update_record('local_parce_ai_actions', $record);
                $ids[] = $actionid;
            } else {
                $ids[] = $DB->insert_record('local_parce_ai_actions', $record);
            }
        }
        return $ids;
    }

    /**
     * Classify a non-terminal provider attempt for metrics.
     *
     * @param array $attempt Normalised BBCO attempt
     * @return string
     */
    private static function classify_attempt_outcome(array $attempt): string {
        if (!empty($attempt['success'])) {
            return 'success';
        }
        if (($attempt['errorcode'] ?? null) === 429) {
            return 'rate_limited';
        }
        $message = $attempt['errormessage'] ?? null;
        if ($message !== null && preg_match('/\b(?:timed?\s*out|timeout)\b/i', $message) === 1) {
            return 'timeout';
        }
        return 'provider_error';
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
        \core_ai\aiactions\responses\response_base $response,
        ?string $provider = null,
        ?int $timecreated = null
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
        $record->timecreated = $timecreated ?? $now;
        $record->provider = $provider;

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
    public static function update_ai_action(
        int $actionid,
        string $intent,
        ?array $intentparams = null,
        ?int $conversationentryid = null
    ): void {
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
