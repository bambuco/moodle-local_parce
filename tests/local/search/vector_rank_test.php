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
 * Tests for merging lexical and vector search hits.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_parce\tests\local\search;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests the three ranking levels and the cosine cutoff.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_parce\local\search\vector_rank::class)]
final class vector_rank_test extends \basic_testcase {
    /**
     * Dual hits lead, lexical-only hits keep their order, and distant passages are dropped.
     */
    public function test_merge_ranks_dual_hits_first_and_drops_distant_passages(): void {
        $lexical = [
            $this->lexical('activity-a', 4, 40, 0, 'Group study notes'),
            $this->lexical('activity-e', 1, 10, 1, 'Delivery calendar'),
            $this->lexical('activity-c', 3, 30, 2, 'Professional contact'),
        ];
        $vector = [
            $this->vector('activity-a', 0.72, 'Sharing notes helps groups study.'),
            $this->vector('activity-c', 0.61, 'Social networks spread professional contact.'),
            $this->vector('activity-b', 0.48, 'The course forum collects tutor questions.'),
            $this->vector('activity-d', 0.22, 'The workshop has three grading criteria.'),
        ];

        $ranked = \local_parce\local\search\vector_rank::merge($lexical, $vector, 0.55, 5);
        $names = array_map(static fn(\stdClass $resource): string => $resource->name, $ranked);

        $this->assertSame(['Group study notes', 'Professional contact', 'Delivery calendar'], $names);
        $this->assertStringContainsString('Sharing notes helps groups study.', $ranked[0]->content);
        $this->assertStringContainsString('Social networks spread professional contact.', $ranked[1]->content);
        $this->assertStringNotContainsString('tutor questions', $ranked[2]->content);
        $this->assertSame(4, $ranked[0]->lexscore);
        $this->assertEqualsWithDelta(0.72, $ranked[0]->relevance, 0.0001);
        $this->assertSame(3, $ranked[1]->lexscore);
        $this->assertEqualsWithDelta(0.61, $ranked[1]->relevance, 0.0001);
        $this->assertSame(1, $ranked[2]->lexscore);
        $this->assertFalse(property_exists($ranked[2], 'relevance'));
    }

    /**
     * A passage that only the vector search found fills a free place when it clears the minimum.
     */
    public function test_vector_only_hits_fill_remaining_places(): void {
        $lexical = [
            $this->lexical('activity-e', 1, 10, 0, 'Delivery calendar'),
        ];
        $vector = [
            $this->vector('activity-b', 0.60, 'The course forum collects tutor questions.'),
        ];

        $ranked = \local_parce\local\search\vector_rank::merge($lexical, $vector, 0.55, 5);

        $this->assertCount(2, $ranked);
        $this->assertSame('Delivery calendar', $ranked[0]->name);
        $this->assertSame('Forum', $ranked[1]->name);
        $this->assertSame('The course forum collects tutor questions.', $ranked[1]->content);
        $this->assertSame(1, $ranked[0]->lexscore);
        $this->assertFalse(property_exists($ranked[0], 'relevance'));
        $this->assertEqualsWithDelta(0.60, $ranked[1]->relevance, 0.0001);
        $this->assertFalse(property_exists($ranked[1], 'lexscore'));
    }

    /**
     * A higher minimum leaves a paraphrase in the lexical tier instead of promoting it.
     */
    public function test_higher_minimum_does_not_promote_a_weaker_cosine(): void {
        $lexical = [
            $this->lexical('activity-a', 4, 40, 0, 'Group study notes'),
            $this->lexical('activity-c', 3, 30, 1, 'Professional contact'),
        ];
        $vector = [
            $this->vector('activity-a', 0.72, 'Sharing notes helps groups study.'),
            $this->vector('activity-c', 0.61, 'Social networks spread professional contact.'),
        ];

        $ranked = \local_parce\local\search\vector_rank::merge($lexical, $vector, 0.70, 5);

        $this->assertSame('Group study notes', $ranked[0]->name);
        $this->assertSame('Professional contact', $ranked[1]->name);
        $this->assertStringContainsString('Sharing notes helps groups study.', $ranked[0]->content);
        $this->assertStringNotContainsString('professional contact.', strtolower($ranked[1]->content));
        $this->assertEqualsWithDelta(0.72, $ranked[0]->relevance, 0.0001);
        $this->assertSame(3, $ranked[1]->lexscore);
        $this->assertFalse(property_exists($ranked[1], 'relevance'));
    }

    /**
     * Build one lexical candidate.
     *
     * @param string $id Parent id.
     * @param int $score Keyword score.
     * @param int $length Content length used as a tie break.
     * @param int $order Original order.
     * @param string $name Result name.
     * @return array
     */
    private function lexical(string $id, int $score, int $length, int $order, string $name): array {
        $resource = new \stdClass();
        $resource->name = $name;
        $resource->content = $name;
        return [
            'id' => $id,
            'lexscore' => $score,
            'contentlength' => $length,
            'order' => $order,
            'resource' => $resource,
        ];
    }

    /**
     * Build one vector candidate.
     *
     * @param string $id Parent id.
     * @param float $cosine Cosine similarity.
     * @param string $passage Passage text.
     * @return array
     */
    private function vector(string $id, float $cosine, string $passage): array {
        $resource = new \stdClass();
        $resource->name = 'Forum';
        $resource->content = $passage;
        return [
            'id' => $id,
            'cosine' => $cosine,
            'passage' => $passage,
            'resource' => $resource,
        ];
    }
}
