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

/**
 * Upgrade script for local_parce
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute local_parce upgrade.
 *
 * @param int $oldversion The installed version of the plugin
 * @return bool True if successful, false otherwise
 */
function xmldb_local_parce_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026080200) {
        $table = new xmldb_table('local_parce_conversation_entries');
        $oldindex = new xmldb_index('userid_chatid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid', 'chatid']);
        if ($dbman->index_exists($table, $oldindex)) {
            $dbman->drop_index($table, $oldindex);
        }
        $index = new xmldb_index(
            'userid_chatid_time_id_idx',
            XMLDB_INDEX_NOTUNIQUE,
            ['userid', 'chatid', 'timecreated', 'id']
        );
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        if (get_config('local_parce', 'activecacheversion') === false) {
            set_config('activecacheversion', 1, 'local_parce');
        }

        upgrade_plugin_savepoint(true, 2026080200, 'local', 'parce');
    }

    if ($oldversion < 2026080601) {
        $table = new xmldb_table('local_parce_ai_actions');
        $fields = [
            new xmldb_field('requestid', XMLDB_TYPE_CHAR, '64', null, null, null, null, 'conversationkey'),
            new xmldb_field('callid', XMLDB_TYPE_CHAR, '64', null, null, null, null, 'requestid'),
            new xmldb_field('attemptordinal', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'callid'),
            new xmldb_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'completed', 'success'),
            new xmldb_field('outcome', XMLDB_TYPE_CHAR, '32', null, null, null, null, 'status'),
            new xmldb_field('completionreason', XMLDB_TYPE_CHAR, '32', null, null, null, null, 'outcome'),
            new xmldb_field('durationms', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'completionreason'),
            new xmldb_field('providercomponent', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'provider'),
            new xmldb_field('providerinstanceid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'providercomponent'),
            new xmldb_field('providername', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'providerinstanceid'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        $model = new xmldb_field('model', XMLDB_TYPE_TEXT, null, null, null, null, null, 'completiontokens');
        $dbman->change_field_type($table, $model);
        $indexes = [
            new xmldb_index('requestid_idx', XMLDB_INDEX_NOTUNIQUE, ['requestid']),
            new xmldb_index('callid_idx', XMLDB_INDEX_NOTUNIQUE, ['callid']),
            new xmldb_index('metrics_idx', XMLDB_INDEX_NOTUNIQUE, ['actiontype', 'outcome', 'timecreated']),
        ];
        foreach ($indexes as $index) {
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }
        upgrade_plugin_savepoint(true, 2026080601, 'local', 'parce');
    }

    if ($oldversion < 2026080700) {
        $table = new xmldb_table('local_parce_conversation_entries');
        $indexes = [
            new xmldb_index('userid_time_chat_id_idx', XMLDB_INDEX_NOTUNIQUE,
                ['userid', 'timecreated', 'chatid', 'id']),
            new xmldb_index('chat_user_key_time_id_idx', XMLDB_INDEX_NOTUNIQUE,
                ['chatid', 'userid', 'conversationkey', 'timecreated', 'id']),
        ];
        foreach ($indexes as $index) {
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }
        upgrade_plugin_savepoint(true, 2026080700, 'local', 'parce');
    }

    if ($oldversion < 2026080701) {
        // Historical rows must survive deletion of their original Moodle context.
        $table = new xmldb_table('local_parce_conversation_entries');
        $key = new xmldb_key('chatid', XMLDB_KEY_FOREIGN, ['chatid'], 'context', ['id']);
        if ($dbman->find_key_name($table, $key)) {
            $dbman->drop_key($table, $key);
        }
        upgrade_plugin_savepoint(true, 2026080701, 'local', 'parce');
    }

    if ($oldversion < 2026080702) {
        // Refresh external service definitions for the history search endpoint.
        upgrade_plugin_savepoint(true, 2026080702, 'local', 'parce');
    }

    if ($oldversion < 2026080703) {
        // Refresh history external return structures with configured-limit metadata.
        upgrade_plugin_savepoint(true, 2026080703, 'local', 'parce');
    }

    return true;
}
