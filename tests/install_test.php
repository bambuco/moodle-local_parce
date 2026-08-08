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

namespace local_parce;

/**
 * Fresh-install schema tests.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class install_test extends \advanced_testcase {
    /**
     * The fresh schema must match the database and include the history index.
     */
    public function test_install_schema_matches_database(): void {
        global $CFG, $DB;

        $xmldbfile = new \xmldb_file($CFG->dirroot . '/local/parce/db/install.xml');
        $this->assertTrue($xmldbfile->loadXMLStructure());
        $DB->get_manager()->check_database_schema($xmldbfile->getStructure());

        $table = new \xmldb_table('local_parce_conversation_entries');
        $index = new \xmldb_index(
            'userid_chatid_time_id_idx',
            XMLDB_INDEX_NOTUNIQUE,
            ['userid', 'chatid', 'timecreated', 'id']
        );
        $this->assertTrue($DB->get_manager()->index_exists($table, $index));
    }
}
