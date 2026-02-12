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
     * Store a conversation entry (question and response) in cache.
     *
     * @param int $userid The user ID
     * @param int $chatid The chat ID
     * @param string $question The user's question
     * @param string $response The system's response
     * @return void
     */
    public static function store_conversation(int $userid, int $chatid, string $question, string $response): void {
        $cache = self::get_cache();
        $config = self::get_cache_config();
        $key = self::get_cache_key($userid, $chatid);

        $data = $cache->get($key);
        if ($data === false) {
            $data = [
                'entries' => [],
                'created' => time(),
            ];
        }

        // Add user question.
        $data['entries'][] = [
            'role' => 'user',
            'content' => $question,
            'timestamp' => time(),
        ];

        // Add system response.
        $data['entries'][] = [
            'role' => 'system',
            'content' => $response,
            'timestamp' => time(),
        ];

        // Enforce maximum entries limit by removing oldest entries.
        $entrycount = count($data['entries']);
        if ($entrycount > $config['maxentries']) {
            $data['entries'] = array_slice($data['entries'], -$config['maxentries']);
        }

        $data['lastaccess'] = time();

        $cache->set($key, $data);
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
     * @param int $userid The user ID
     * @return void
     */
    public static function clear_user_conversations(int $userid): void {
        $cache = self::get_cache();
        $cache->purge();
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
    public static function get_conversation_entries_paginated(
        int $userid,
        int $chatid,
        int $offset = 0,
        int $limit = 20
    ): array {
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
}
