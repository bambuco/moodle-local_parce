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
 * Shared authorization, snapshot, cursor, and audit behavior for history APIs.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class history_service {
    /**
     * Default and hard maximum for history list settings.
     *
     * @var int The default history list limit.
     */
    private const DEFAULT_LIST_LIMIT = 20;

    /**
     * @var int The maximum history list limit.
     */
    private const MAX_LIST_LIMIT = 100;

    /**
     * Return a validated administrator-configured history limit.
     *
     * @param string $name The name of the configuration setting.
     * @return int The validated history limit.
     */
    public static function configured_limit(string $name): int {
        $limit = (int) get_config('local_parce', $name);
        if ($limit < 1 || $limit > self::MAX_LIST_LIMIT) {
            return self::DEFAULT_LIST_LIMIT;
        }
        return $limit;
    }

    /**
     * Validate login and page limit.
     *
     * @param int $limit The requested page limit.
     * @return void
     */
    public static function require_access(int $limit): void {
        require_login();
        if (isguestuser()) {
            throw new \moodle_exception('error_guest_history', 'local_parce');
        }
        if ($limit < 1 || $limit > 100) {
            throw new \invalid_parameter_exception('Invalid history pagination.');
        }
    }

    /**
     * Resolve a requested page size without exceeding its configured limit.
     *
     * @param int $requested The requested page size.
     * @param string $setting The configuration setting name for the limit.
     * @return int The resolved page size.
     */
    public static function limit(int $requested, string $setting): int {
        $configured = self::configured_limit($setting);
        if ($requested === 0) {
            return $configured;
        }
        self::require_access($requested);
        return min($requested, $configured);
    }

    /**
     * Resolve a context, allowing a missing context only for own history.
     *
     * @param int $chatid The ID of the chat context.
     * @param bool $admin Whether the current user is an administrator.
     * @return \context|null The resolved context or null if missing and not admin.
     */
    public static function context(int $chatid, bool $admin): ?\context {
        $context = \context::instance_by_id($chatid, IGNORE_MISSING);
        if (!$context) {
            if ($admin) {
                throw new \invalid_parameter_exception('Invalid history request.');
            }
            return null;
        }
        if (!in_array($context->contextlevel, [CONTEXT_SYSTEM, CONTEXT_COURSE], true)) {
            throw new \invalid_parameter_exception('Invalid history request.');
        }
        if ($admin) {
            require_capability('local/parce:viewallchats', $context);
        }
        return $context;
    }

    /**
     * Current snapshot maximum id.
     *
     * @return int The maximum snapshot ID.
     */
    public static function snapshot(): int {
        global $DB;
        return (int) $DB->get_field_sql('SELECT COALESCE(MAX(id), 0) FROM {local_parce_conversation_entries}');
    }

    /**
     * Emit one audit event for each distinct foreign user exposed by this page.
     *
     * @param \context $context The context in which the history is viewed.
     * @param array $userids The user IDs of the foreign users exposed.
     * @param array $conversationkeys Optional conversation keys associated with each user.
     * @return void
     */
    public static function audit(\context $context, array $userids, array $conversationkeys = []): void {
        global $USER;
        foreach (array_unique($userids) as $userid) {
            if ((int) $userid === (int) $USER->id) {
                continue;
            }
            $eventdata = [
                'context' => $context,
                'relateduserid' => (int) $userid,
            ];
            if (!empty($conversationkeys[$userid])) {
                $eventdata['other'] = [
                    'conversationkeys' => implode(',', array_unique($conversationkeys[$userid])),
                ];
            }
            \local_parce\event\conversation_history_viewed::create($eventdata)->trigger();
        }
    }
}
