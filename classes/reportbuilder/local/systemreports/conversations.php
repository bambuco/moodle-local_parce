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

declare(strict_types=1);

namespace local_parce\reportbuilder\local\systemreports;

use context_system;
use core_reportbuilder\local\aggregation\count;
use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\report\action;
use core_reportbuilder\system_report;
use lang_string;
use local_parce\reportbuilder\local\entities\conversation_entry;
use moodle_url;
use pix_icon;

/**
 * System report listing Parce conversation activity by user and context.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class conversations extends system_report {
    /**
     * Initialise report, set the main table, load entities and set columns/filters/actions.
     */
    protected function initialise(): void {
        $entityuser = new user();
        $useralias = $entityuser->get_table_alias('user');

        $this->set_main_table('user', $useralias);
        $this->add_entity($entityuser);

        $entityentry = new conversation_entry();
        $entryalias = $entityentry->get_table_alias('local_parce_conversation_entries');
        $this->add_entity($entityentry
             ->add_join("JOIN {local_parce_conversation_entries} {$entryalias} ON {$entryalias}.userid = {$useralias}.id"));

        // Fields required by the history action; included in GROUP BY when aggregating.
        $this->add_base_fields("{$useralias}.id, {$entryalias}.chatid");

        $this->add_columns();
        $this->add_filters();
        $this->add_actions();

        $this->set_downloadable(false);
    }

    /**
     * Validate access to view this report.
     *
     * @return bool
     */
    protected function can_view(): bool {
        return has_capability('local/parce:viewallchats', context_system::instance());
    }

    /**
     * Add the columns to display in the report.
     */
    protected function add_columns(): void {
        $this->add_columns_from_entities([
            'user:fullnamewithlink',
            'conversation_entry:chatid',
        ]);

        $this->add_column_from_entity('conversation_entry:id')
            ->set_title(new lang_string('conversationsreportturns', 'local_parce'))
            ->set_aggregation(count::get_class_name());

        $this->set_initial_sort_column('user:fullnamewithlink', SORT_ASC);
    }

    /**
     * Add the filters to display in the report.
     */
    protected function add_filters(): void {
        $this->add_filters_from_entities([
            'user:fullname',
        ]);
    }

    /**
     * Add row actions.
     */
    protected function add_actions(): void {
        $this->add_action(new action(
            new moodle_url('/local/parce/history.php', [
                'mode' => 'admin',
                'chatid' => ':chatid',
                'userid' => ':id',
            ]),
            new pix_icon('t/log', '', 'core'),
            [],
            false,
            new lang_string('conversationsreporthistory', 'local_parce'),
        ));
    }
}
