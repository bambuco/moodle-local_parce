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

namespace local_parce\local\search;

/**
 * Orders lexical and vector hits without adding their scores together.
 *
 * A hit found by both searches comes first. Lexical-only hits keep their
 * current order. Vector-only hits fill the remaining places. A cosine below
 * the configured minimum is discarded and does not promote a lexical hit.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class vector_rank {
    /**
     * Merge the two result lists down to the requested limit.
     *
     * Each lexical row has id, lexscore, contentlength, order and resource.
     * Each vector row has id, cosine, passage and resource. The resource is a
     * stdClass whose content property receives the passage when the hit is kept
     * for its vector score.
     *
     * @param array $lexical Lexical candidates that already passed the keyword filter.
     * @param array $vector Vector candidates, including those below the minimum.
     * @param float $minimum Lowest cosine that is allowed to count.
     * @param int $limit Maximum results to return.
     * @return \stdClass[]
     */
    public static function merge(array $lexical, array $vector, float $minimum, int $limit): array {
        $lexicalbyid = [];
        foreach ($lexical as $row) {
            if (!is_array($row) || !isset($row['id'], $row['resource'])) {
                continue;
            }
            $lexicalbyid[(string) $row['id']] = $row;
        }

        $vectorbyid = [];
        foreach ($vector as $row) {
            if (!is_array($row) || !isset($row['id'], $row['resource'])) {
                continue;
            }
            $cosine = (float) ($row['cosine'] ?? 0);
            if ($cosine < $minimum) {
                continue;
            }
            $id = (string) $row['id'];
            if (!isset($vectorbyid[$id]) || $cosine > (float) $vectorbyid[$id]['cosine']) {
                $row['cosine'] = $cosine;
                $vectorbyid[$id] = $row;
            }
        }

        $dual = [];
        foreach ($vectorbyid as $id => $vectorrow) {
            if (isset($lexicalbyid[$id])) {
                $dual[] = ['lexical' => $lexicalbyid[$id], 'vector' => $vectorrow];
            }
        }
        usort($dual, static function (array $first, array $second): int {
            return ((float) $second['vector']['cosine'] <=> (float) $first['vector']['cosine'])
                ?: ((int) $second['lexical']['lexscore'] <=> (int) $first['lexical']['lexscore'])
                ?: ((int) $second['lexical']['contentlength'] <=> (int) $first['lexical']['contentlength'])
                ?: ((int) $first['lexical']['order'] <=> (int) $second['lexical']['order']);
        });

        $lexicalonly = [];
        foreach ($lexicalbyid as $id => $row) {
            if (!isset($vectorbyid[$id])) {
                $lexicalonly[] = $row;
            }
        }
        usort($lexicalonly, static function (array $first, array $second): int {
            return ((int) $second['lexscore'] <=> (int) $first['lexscore'])
                ?: ((int) $second['contentlength'] <=> (int) $first['contentlength'])
                ?: ((int) $first['order'] <=> (int) $second['order']);
        });

        $vectoronly = [];
        foreach ($vectorbyid as $id => $row) {
            if (!isset($lexicalbyid[$id])) {
                $vectoronly[] = $row;
            }
        }
        usort($vectoronly, static function (array $first, array $second): int {
            return ((float) $second['cosine'] <=> (float) $first['cosine']);
        });

        $ranked = [];
        foreach ($dual as $pair) {
            $ranked[] = self::with_passage($pair['lexical']['resource'], (string) ($pair['vector']['passage'] ?? ''));
        }
        foreach ($lexicalonly as $row) {
            $ranked[] = $row['resource'];
        }
        foreach ($vectoronly as $row) {
            $ranked[] = self::with_passage($row['resource'], (string) ($row['passage'] ?? ''));
        }
        return array_slice($ranked, 0, max(0, $limit));
    }

    /**
     * Append a passage that is not already part of the resource content.
     *
     * @param \stdClass $resource Result that will be shown to the answer model.
     * @param string $passage Matching passage.
     * @return \stdClass The same resource.
     */
    private static function with_passage(\stdClass $resource, string $passage): \stdClass {
        $passage = trim($passage);
        $content = trim((string) ($resource->content ?? ''));
        if ($passage !== '' && !str_contains($content, $passage)) {
            $resource->content = $content === '' ? $passage : $content . "\n" . $passage;
        }
        return $resource;
    }
}
