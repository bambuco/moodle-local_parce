<?php
// This file is part of Moodle - http://moodle.org/

namespace local_parce\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_parce\local\history_cursor;
use local_parce\local\history_repository;
use local_parce\local\history_service;

/** List contexts containing persistent history. */
final class list_history_contexts extends external_api {
    /** Parameters. */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cursor' => new external_value(PARAM_RAW, 'Opaque continuation cursor', VALUE_DEFAULT, ''),
            'limit' => new external_value(PARAM_INT, 'Page size; zero uses the configured maximum', VALUE_DEFAULT, 0),
        ]);
    }

    /** Execute. */
    public static function execute(string $cursor = '', int $limit = 0): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), compact('cursor', 'limit'));
        history_service::require_access(max(1, $params['limit']));
        $limit = history_service::limit($params['limit'], 'history_context_limit');
        self::validate_context(\context_system::instance());
        $target = (int) $USER->id;
        $scope = ['target' => $target, 'mode' => 'own', 'filters' => []];
        $state = $params['cursor'] === '' ? ['snapshot' => history_service::snapshot(), 'after' => null]
            : history_cursor::decode($params['cursor'], 'contexts', $scope);
        $records = history_repository::contexts($target, (int) $state['snapshot'], $state['after'], $limit);
        $hasmore = count($records) > $limit;
        if ($hasmore) {
            array_pop($records);
        }
        $items = [];
        foreach ($records as $record) {
            $context = history_service::context((int) $record->chatid, false);
            $items[] = ['chatid' => (int) $record->chatid,
                'name' => $context ? $context->get_context_name(false) : get_string('historyunavailablecontext', 'local_parce'),
                'lastactivity' => (int) $record->lastactivity, 'turncount' => (int) $record->turncount];
        }
        $next = '';
        if ($hasmore && $records) {
            $last = end($records);
            $next = history_cursor::encode('contexts', $scope,
                ['snapshot' => (int) $state['snapshot'], 'after' => [(int) $last->lastactivity, (int) $last->chatid]]);
        }
        return ['contexts' => $items, 'cursor' => $next, 'hasmore' => $hasmore,
            'limited' => count($items) >= $limit, 'resultlimit' => $limit];
    }

    /** Returns. */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'contexts' => new external_multiple_structure(new external_single_structure([
                'chatid' => new external_value(PARAM_INT, 'Context ID'),
                'name' => new external_value(PARAM_TEXT, 'Safe context label'),
                'lastactivity' => new external_value(PARAM_INT, 'Last activity'),
                'turncount' => new external_value(PARAM_INT, 'Turn count'),
            ])),
            'cursor' => new external_value(PARAM_RAW, 'Next cursor'),
            'hasmore' => new external_value(PARAM_BOOL, 'More results'),
            'limited' => new external_value(PARAM_BOOL, 'Whether the configured maximum was displayed'),
            'resultlimit' => new external_value(PARAM_INT, 'Configured result maximum'),
        ]);
    }
}
