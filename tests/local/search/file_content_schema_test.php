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
 * Tests for the stored Solr file-text schema.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_parce\tests\local\search;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests schema commands and check interpretation without calling Solr.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_parce\local\search\file_content_schema::class)]
final class file_content_schema_test extends \advanced_testcase {
    /**
     * Schema commands name the stored field and do not change Moodle's file field.
     */
    public function test_schema_commands_target_the_stored_copy(): void {
        $schema = \local_parce\local\search\file_content_schema::class;

        $addfield = $schema::add_field_command();
        $replacefield = $schema::replace_field_command();
        $copyfield = $schema::add_copy_field_command();

        $this->assertSame($schema::FIELD_NAME, $addfield['add-field']['name']);
        $this->assertSame('text_general', $addfield['add-field']['type']);
        $this->assertTrue($addfield['add-field']['stored']);
        $this->assertTrue($addfield['add-field']['indexed']);
        $this->assertFalse($addfield['add-field']['multiValued']);
        $this->assertSame($addfield['add-field'], $replacefield['replace-field']);
        $this->assertSame($schema::SOURCE_FIELD, $copyfield['add-copy-field']['source']);
        $this->assertSame($schema::FIELD_NAME, $copyfield['add-copy-field']['dest']);
        $this->assertArrayNotHasKey($schema::SOURCE_FIELD, $addfield);
    }

    /**
     * A ready field and copy are recognised, including a destination list.
     */
    public function test_ready_field_and_copy_are_recognised(): void {
        $schema = \local_parce\local\search\file_content_schema::class;
        $field = $schema::read_field([
            'field' => $schema::field_attributes(),
        ]);
        $copies = $schema::read_copy_fields([
            'copyFields' => [[
                'source' => $schema::SOURCE_FIELD,
                'dest' => [$schema::FIELD_NAME],
            ]],
        ]);

        $this->assertTrue($schema::field_is_ready($field));
        $this->assertTrue($schema::copy_field_exists($copies));
        $this->assertFalse($schema::field_is_ready(['name' => $schema::FIELD_NAME, 'stored' => false]));
        $this->assertFalse($schema::copy_field_exists([[
            'source' => $schema::SOURCE_FIELD,
            'dest' => 'other_field',
        ]]));
    }

    /**
     * The check summary distinguishes an empty copy from stored file text.
     */
    public function test_summary_reports_stored_text_separately_from_schema(): void {
        $schema = \local_parce\local\search\file_content_schema::class;
        $field = $schema::field_attributes();
        $copies = [[
            'source' => $schema::SOURCE_FIELD,
            'dest' => $schema::FIELD_NAME,
        ]];

        $empty = $schema::summarise(true, $field, $copies, [
            'response' => ['numFound' => 0, 'docs' => []],
        ]);
        $ready = $schema::summarise(false, $field, $copies, [
            'response' => [
                'numFound' => 2,
                'docs' => [[$schema::FIELD_NAME => 'Ziruma es un territorio.']],
            ],
        ]);
        $missing = $schema::summarise(true, null, [], null, '');

        $this->assertTrue($empty['fieldok']);
        $this->assertTrue($empty['copyfieldok']);
        $this->assertTrue($empty['fileindexing']);
        $this->assertSame(0, $empty['documentcount']);
        $this->assertFalse($empty['hasstoredtext']);

        $this->assertSame(2, $ready['documentcount']);
        $this->assertTrue($ready['hasstoredtext']);
        $this->assertFalse($ready['fileindexing']);

        $this->assertFalse($missing['fieldok']);
        $this->assertFalse($missing['copyfieldok']);
        $this->assertNull($missing['documentcount']);
    }

    /**
     * Schema error bodies are reduced to a single message.
     */
    public function test_schema_error_message_is_read_from_the_response(): void {
        $schema = \local_parce\local\search\file_content_schema::class;

        $message = $schema::error_from_response([
            'code' => 400,
            'data' => [
                'errors' => [[
                    'errorMessages' => ['Field already exists'],
                ]],
            ],
        ]);

        $this->assertSame('Field already exists', $message);
        $this->assertSame('HTTP 500', $schema::error_from_response(['code' => 500, 'data' => []]));
    }
}
