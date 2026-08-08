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

/** Return turns from exactly one conversation. */
final class get_history_turns extends external_api {
    /** Parameters. */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'chatid' => new external_value(PARAM_INT, 'Context ID'),
            'conversationkey' => new external_value(PARAM_ALPHANUM, 'Conversation key'),
            'userid' => new external_value(PARAM_INT, 'Owner; zero means current user', VALUE_DEFAULT, 0),
            'cursor' => new external_value(PARAM_RAW, 'Opaque continuation cursor', VALUE_DEFAULT, ''),
            'limit' => new external_value(PARAM_INT, 'Page size', VALUE_DEFAULT, 20),
        ]);
    }

    /** Execute. */
    public static function execute(int $chatid, string $conversationkey, int $userid = 0,
            string $cursor = '', int $limit = 20): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), compact('chatid', 'conversationkey', 'userid', 'cursor', 'limit'));
        history_service::require_access($params['limit']);
        $target = $params['userid'] ?: (int) $USER->id;
        $foreign = $target !== (int) $USER->id;
        $context = history_service::context($params['chatid'], $foreign);
        // Validate at system level so an owner does not need current enrolment.
        self::validate_context(\context_system::instance());
        $scope = ['target' => $target, 'mode' => $foreign ? 'admin' : 'own', 'chatid' => $params['chatid'],
            'conversationkey' => $params['conversationkey'], 'filters' => []];
        $state = $params['cursor'] === '' ? ['snapshot' => history_service::snapshot(), 'after' => null]
            : history_cursor::decode($params['cursor'], 'turns', $scope);
        $records = history_repository::turns($target, $params['chatid'], $params['conversationkey'],
            (int) $state['snapshot'], $state['after'], $params['limit']);
        $hasmore = count($records) > $params['limit'];
        if ($hasmore) {
            array_pop($records);
        }
        $usage = history_repository::turn_usage($records);
        $turns = [];
        foreach ($records as $record) {
            $turnusage = $usage[$record->id] ?? null;
            $turns[] = ['id' => (int) $record->id, 'question' => clean_text($record->question, FORMAT_PLAIN),
                'response' => clean_text($record->response, FORMAT_HTML), 'timecreated' => (int) $record->timecreated,
                'prompttokens' => (int) ($turnusage->prompttokens ?? 0),
                'completiontokens' => (int) ($turnusage->completiontokens ?? 0)];
        }
        if ($foreign && $records) {
            history_service::audit($context, [$target], [$target => [$params['conversationkey']]]);
        }
        $next = '';
        if ($hasmore && $records) {
            $last = end($records);
            $next = history_cursor::encode('turns', $scope, ['snapshot' => (int) $state['snapshot'],
                'after' => [(int) $last->timecreated, (int) $last->id]]);
        }
        return ['turns' => $turns, 'cursor' => $next, 'hasmore' => $hasmore];
    }

    /** Returns. */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'turns' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Turn ID'),
                'question' => new external_value(PARAM_TEXT, 'Question'),
                'response' => new external_value(PARAM_RAW, 'Clean response HTML'),
                'timecreated' => new external_value(PARAM_INT, 'Creation time'),
                'prompttokens' => new external_value(PARAM_INT, 'Prompt tokens'),
                'completiontokens' => new external_value(PARAM_INT, 'Completion tokens'),
            ])),
            'cursor' => new external_value(PARAM_RAW, 'Next cursor'),
            'hasmore' => new external_value(PARAM_BOOL, 'More results'),
        ]);
    }
}
