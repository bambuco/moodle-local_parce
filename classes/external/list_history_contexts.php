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
 * List contexts containing persistent history.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class list_history_contexts extends external_api {
    /**
     * Parameters for the service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cursor' => new external_value(PARAM_RAW, 'Opaque continuation cursor', VALUE_DEFAULT, ''),
            'limit' => new external_value(PARAM_INT, 'Page size; zero uses the configured maximum', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute the service.
     *
     * @param string $cursor
     * @param int $limit
     * @return array
     */
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
            $next = history_cursor::encode(
                'contexts',
                $scope,
                ['snapshot' => (int) $state['snapshot'], 'after' => [(int) $last->lastactivity, (int) $last->chatid]]
            );
        }
        return ['contexts' => $items, 'cursor' => $next, 'hasmore' => $hasmore,
            'limited' => count($items) >= $limit, 'resultlimit' => $limit];
    }

    /**
     * Returns the structure of the list of history contexts.
     *
     * @return external_single_structure
     */
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
