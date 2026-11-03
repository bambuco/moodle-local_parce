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
 * Tests for the passage-vector schema.
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
#[\PHPUnit\Framework\Attributes\CoversClass(\local_parce\local\search\vector_schema::class)]
final class vector_schema_test extends \advanced_testcase {
    /**
     * Schema commands name the vector type and the passage fields.
     */
    public function test_schema_commands_define_the_passage_fields(): void {
        $schema = \local_parce\local\search\vector_schema::class;
        $type = $schema::add_field_type_command();
        $fields = $schema::add_field_commands();

        $this->assertSame($schema::FIELD_TYPE, $type['add-field-type']['name']);
        $this->assertSame('solr.DenseVectorField', $type['add-field-type']['class']);
        $this->assertSame($schema::DIMENSION, $type['add-field-type']['vectorDimension']);
        $this->assertSame('cosine', $type['add-field-type']['similarityFunction']);
        $this->assertCount(5, $fields);
        $names = array_map(static fn(array $command): string => $command['add-field']['name'], $fields);
        $this->assertContains($schema::VECTOR_FIELD, $names);
        $this->assertContains($schema::CHUNK_FIELD, $names);
        $this->assertContains($schema::PARENT_FIELD, $names);
        $this->assertContains($schema::SOURCE_FIELD, $names);
        $this->assertContains($schema::ORIGIN_FIELD, $names);

        $chunk = $schema::field_attributes()[$schema::CHUNK_FIELD];
        $this->assertTrue($chunk['stored']);
        $this->assertFalse($chunk['indexed']);
    }

    /**
     * A ready type is recognised and a different dimension is not.
     */
    public function test_vector_type_dimension_is_recognised(): void {
        $schema = \local_parce\local\search\vector_schema::class;
        $ready = $schema::read_definition([
            'fieldType' => [
                'name' => $schema::FIELD_TYPE,
                'class' => 'org.apache.solr.schema.DenseVectorField',
                'vectorDimension' => '384',
                'similarityFunction' => 'cosine',
            ],
        ], 'fieldType');
        $other = $ready;
        $other['vectorDimension'] = 768;

        $this->assertTrue($schema::field_type_is_ready($ready));
        $this->assertFalse($schema::field_type_is_ready($other));
        $this->assertFalse($schema::field_type_is_ready(null));
    }

    /**
     * The summary keeps file text optional and reports passage documents.
     */
    public function test_summary_keeps_file_text_optional(): void {
        $schema = \local_parce\local\search\vector_schema::class;
        $type = $schema::field_type_attributes();
        $type['class'] = 'solr.DenseVectorField';
        $fields = $schema::field_attributes();

        $ready = $schema::summarise($type, $fields, 3, false);
        $this->assertTrue($ready['typeok']);
        $this->assertTrue($ready['fieldsok']);
        $this->assertFalse($ready['dimensionmismatch']);
        $this->assertFalse($ready['filecontent']);
        $this->assertSame(3, $ready['documentcount']);

        $mismatch = $schema::summarise(['name' => $schema::FIELD_TYPE, 'vectorDimension' => 32], [], null, true);
        $this->assertTrue($mismatch['dimensionmismatch']);
        $this->assertFalse($mismatch['fieldsok']);
        $this->assertTrue($mismatch['filecontent']);
    }
}
