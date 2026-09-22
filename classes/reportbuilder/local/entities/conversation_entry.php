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

namespace local_parce\reportbuilder\local\entities;

use context;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\report\column;
use lang_string;

/**
 * Conversation entry entity for Parce reportbuilder reports.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class conversation_entry extends base {
    /**
     * Database tables that this entity uses.
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return [
            'local_parce_conversation_entries',
        ];
    }

    /**
     * The default title for this entity.
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entityconversationentry', 'local_parce');
    }

    /**
     * Initialise the entity.
     *
     * @return base
     */
    public function initialise(): base {
        foreach ($this->get_all_columns() as $column) {
            $this->add_column($column);
        }

        return $this;
    }

    /**
     * Returns list of all available columns.
     *
     * @return column[]
     */
    protected function get_all_columns(): array {
        $alias = $this->get_table_alias('local_parce_conversation_entries');
        $columns = [];

        // Turn identifier used for count aggregation.
        $columns[] = (new column(
            'id',
            new lang_string('conversationsreportturns', 'local_parce'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->set_is_sortable(true)
            ->add_field("{$alias}.id");

        // Context (chatid is the Moodle context id of the chat scope).
        $columns[] = (new column(
            'chatid',
            new lang_string('conversationsreportcontext', 'local_parce'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->set_is_sortable(true)
            ->add_field("{$alias}.chatid")
            ->add_callback(static function ($value): string {
                $context = context::instance_by_id((int) $value, IGNORE_MISSING);
                if (!$context) {
                    return get_string('historyunavailablecontext', 'local_parce');
                }
                return $context->get_context_name(false);
            });

        return $columns;
    }
}
