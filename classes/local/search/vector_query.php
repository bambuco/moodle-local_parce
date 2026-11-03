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
 * Finds passage documents with the same access filters as global search.
 *
 * The query text is the resolved question. Passages below the configured cosine
 * are dropped. A failure of the embedding endpoint or of Solr returns no passages
 * and leaves the lexical results in place.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class vector_query {
    /** Nearest passages requested before the cosine cutoff. */
    public const TOP_K = 20;

    /**
     * Whether the administrator turned vector search on and named an embedding endpoint.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        $url = trim((string) get_config('local_parce', 'vector_embed_url'));
        return !empty(get_config('local_parce', 'vectorsearch')) && $url !== '';
    }

    /**
     * Search passages for one resolved question.
     *
     * The caller must already be impersonating the user whose access is checked.
     *
     * @param \stdClass $formdata Search filters already built for the lexical query.
     * @param string $resolvedquestion Standalone question to embed.
     * @return array<int, array{id: string, cosine: float, passage: string, resource: \stdClass}>
     */
    public function passages(\stdClass $formdata, string $resolvedquestion): array {
        $resolvedquestion = trim($resolvedquestion);
        if ($resolvedquestion === '' || !self::is_enabled()) {
            return [];
        }
        $vector = $this->embed($resolvedquestion);
        if ($vector === null) {
            return [];
        }

        try {
            $hits = $this->nearest($formdata, $vector);
        } catch (\Throwable $e) {
            debugging('Vector search failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
        return $hits;
    }

    /**
     * Ask the worker for one embedding.
     *
     * @param string $text Text to embed.
     * @return float[]|null Vector of the expected dimension, or null when the worker does not answer.
     */
    public function embed(string $text): ?array {
        $url = trim((string) get_config('local_parce', 'vector_embed_url'));
        if ($url === '') {
            return null;
        }
        $curl = new \curl(['ignoresecurity' => true]);
        $curl->setopt(['CURLOPT_TIMEOUT' => 15]);
        $curl->setHeader('Content-type: application/json');
        $response = $curl->post($url, json_encode(['text' => $text]));
        if (!is_string($response) || $curl->get_errno()) {
            return null;
        }
        $decoded = json_decode($response, true);
        $vector = is_array($decoded) ? ($decoded['vector'] ?? null) : null;
        if (!is_array($vector) || count($vector) !== vector_schema::DIMENSION) {
            return null;
        }
        $numbers = [];
        foreach ($vector as $number) {
            if (!is_numeric($number)) {
                return null;
            }
            $numbers[] = (float) $number;
        }
        return $numbers;
    }

    /**
     * Run the nearest-passage query with the lexical access filters.
     *
     * create_user_query() is reused so the context, owner and group filters stay
     * the ones Solr search already applies. The returned query is edismax, so its
     * filters are copied onto a standard query whose main clause is the vector
     * search. process_response() is not used: a deleted passage must not be removed
     * with the engine's delete-by-grouping-id, which targets activity documents.
     *
     * @param \stdClass $formdata Lexical filters.
     * @param float[] $vector Query vector.
     * @return array<int, array{id: string, cosine: float, passage: string, resource: \stdClass}>
     */
    private function nearest(\stdClass $formdata, array $vector): array {
        $manager = \core_search\manager::instance(true, true);
        $engine = $manager->get_engine();
        if (!$engine instanceof \search_solr\engine) {
            return [];
        }

        $limitcourses = !empty($formdata->courseids) ? $formdata->courseids : false;
        $accessmethod = new \ReflectionMethod($manager, 'get_areas_user_accesses');
        $accessinfo = $accessmethod->invoke($manager, $limitcourses, false);

        $createmethod = new \ReflectionMethod($engine, 'create_user_query');
        $built = $createmethod->invoke($engine, $formdata, $accessinfo);
        if (!$built instanceof \SolrQuery) {
            return [];
        }

        $query = new \SolrQuery();
        $literal = '[' . implode(',', array_map(static fn(float $number): string => sprintf('%.8F', $number), $vector)) . ']';
        $query->setQuery('{!knn f=' . vector_schema::VECTOR_FIELD . ' topK=' . self::TOP_K . '}' . $literal);
        $query->setRows(self::TOP_K);
        foreach ($built->getFilterQueries() as $filter) {
            $query->addFilterQuery($filter);
        }
        $query->addFilterQuery(vector_schema::PARENT_FIELD . ':*');
        $fields = [
            'id',
            'score',
            'itemid',
            'areaid',
            'courseid',
            'contextid',
            'title',
            vector_schema::CHUNK_FIELD,
            vector_schema::PARENT_FIELD,
        ];
        foreach ($fields as $field) {
            $query->addField($field);
        }

        $runmethod = new \ReflectionMethod($engine, 'get_query_response');
        $response = $runmethod->invoke($engine, $query);
        if (!$response || !isset($response->response->docs)) {
            return [];
        }

        $minimum = vector_schema::minimum_score();
        $best = [];
        foreach ($response->response->docs as $doc) {
            $cosine = self::float_field($doc, 'score');
            $parentid = self::string_field($doc, vector_schema::PARENT_FIELD);
            $passage = self::string_field($doc, vector_schema::CHUNK_FIELD);
            if ($cosine === null || $cosine < $minimum || $parentid === '' || $passage === '') {
                continue;
            }
            if (isset($best[$parentid]) && $cosine <= $best[$parentid]['cosine']) {
                continue;
            }
            $areaid = self::string_field($doc, 'areaid');
            $itemid = self::string_field($doc, 'itemid');
            $area = $areaid === '' ? false : \core_search\manager::get_search_area($areaid);
            if (!$area || $itemid === '') {
                continue;
            }
            $access = $area->check_access((int) $itemid);
            if ($access !== \core_search\manager::ACCESS_GRANTED) {
                continue;
            }
            $courseid = (int) self::string_field($doc, 'courseid');
            $resource = $this->resource_for($area, $areaid, (int) $itemid, $courseid, $passage);
            if ($resource === null) {
                continue;
            }
            $best[$parentid] = [
                'id' => $parentid,
                'cosine' => $cosine,
                'passage' => $passage,
                'resource' => $resource,
            ];
        }
        return array_values($best);
    }

    /**
     * Build the result object the answer model receives for one passage.
     *
     * @param \core_search\base $area Search area of the parent document.
     * @param string $areaid Area identifier.
     * @param int $itemid Parent item id.
     * @param int $courseid Parent course id.
     * @param string $passage Passage text.
     * @return \stdClass|null Result, or null when the parent link cannot be built.
     */
    private function resource_for(
        \core_search\base $area,
        string $areaid,
        int $itemid,
        int $courseid,
        string $passage
    ): ?\stdClass {
        [$component, $areaname] = \core_search\manager::extract_areaid_parts($areaid);
        try {
            $document = \core_search\document_factory::instance($itemid, $component, $areaname);
            $document->set('itemid', $itemid);
            $document->set('courseid', $courseid);
            $document->set('areaid', $areaid);
            $url = (string) $area->get_doc_url($document);
        } catch (\Throwable $e) {
            return null;
        }
        if ($url === '') {
            return null;
        }

        $course = $courseid > 0 ? get_course($courseid) : null;
        $resource = new \stdClass();
        $resource->name = trim(self::parent_title($areaid, $itemid));
        if ($resource->name === '') {
            $resource->name = get_string('notitle', 'search');
        }
        $resource->url = $url;
        $resource->type = $component;
        $resource->subtype = $areaname;
        $resource->content = $passage;
        $resource->coursename = $course ? $course->fullname : '';
        $resource->courseurl = $course ? (string) \course_get_url($courseid) : '';
        return $resource;
    }

    /**
     * Read the stored title of a parent search document.
     *
     * @param string $areaid Area identifier.
     * @param int $itemid Parent item id.
     * @return string Title, or an empty string when it cannot be read.
     */
    private static function parent_title(string $areaid, int $itemid): string {
        $engine = \core_search\manager::instance(true, true)->get_engine();
        if (!$engine instanceof \search_solr\engine) {
            return '';
        }
        $query = new \SolrQuery();
        $query->setQuery('areaid:"' . addcslashes($areaid, '"\\') . '" AND itemid:' . $itemid);
        $query->addFilterQuery('-' . vector_schema::PARENT_FIELD . ':*');
        $query->addFilterQuery('-solr_fileid:[* TO *]');
        $query->setRows(1);
        $query->addField('title');
        $method = new \ReflectionMethod($engine, 'get_query_response');
        $response = $method->invoke($engine, $query);
        if (!$response || empty($response->response->docs[0])) {
            return '';
        }
        return self::string_field($response->response->docs[0], 'title');
    }

    /**
     * Read a string field from a Solr document.
     *
     * @param \SolrObject|object $doc Solr document.
     * @param string $field Field name.
     * @return string
     */
    private static function string_field(object $doc, string $field): string {
        if (!isset($doc->$field)) {
            return '';
        }
        $value = $doc->$field;
        if (is_array($value)) {
            $value = reset($value);
        }
        return trim((string) $value);
    }

    /**
     * Read a numeric field from a Solr document.
     *
     * @param \SolrObject|object $doc Solr document.
     * @param string $field Field name.
     * @return float|null
     */
    private static function float_field(object $doc, string $field): ?float {
        if (!isset($doc->$field)) {
            return null;
        }
        $value = $doc->$field;
        if (is_array($value)) {
            $value = reset($value);
        }
        return is_numeric($value) ? (float) $value : null;
    }
}
