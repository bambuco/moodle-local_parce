<?php
// This file is part of Moodle - http://moodle.org/

namespace local_parce\local;

/** Shared authorization, snapshot, cursor, and audit behavior for history APIs. */
final class history_service {
    /** Default and hard maximum for history list settings. */
    private const DEFAULT_LIST_LIMIT = 20;
    private const MAX_LIST_LIMIT = 100;

    /** Return a validated administrator-configured history limit. */
    public static function configured_limit(string $name): int {
        $limit = (int) get_config('local_parce', $name);
        if ($limit < 1 || $limit > self::MAX_LIST_LIMIT) {
            return self::DEFAULT_LIST_LIMIT;
        }
        return $limit;
    }

    /** Validate login and page limit. */
    public static function require_access(int $limit): void {
        require_login();
        if (isguestuser()) {
            throw new \moodle_exception('error_guest_history', 'local_parce');
        }
        if ($limit < 1 || $limit > 100) {
            throw new \invalid_parameter_exception('Invalid history pagination.');
        }
    }

    /** Resolve a requested page size without exceeding its configured limit. */
    public static function limit(int $requested, string $setting): int {
        $configured = self::configured_limit($setting);
        if ($requested === 0) {
            return $configured;
        }
        self::require_access($requested);
        return min($requested, $configured);
    }

    /** Resolve a context, allowing a missing context only for own history. */
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

    /** Current snapshot maximum id. */
    public static function snapshot(): int {
        global $DB;
        return (int) $DB->get_field_sql('SELECT COALESCE(MAX(id), 0) FROM {local_parce_conversation_entries}');
    }

    /** Emit one audit event for each distinct foreign user exposed by this page. */
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
