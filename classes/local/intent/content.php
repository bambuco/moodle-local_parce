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

namespace local_parce\local\intent;

/**
 * Class content
 * Content intent can be used to get specific content based on keywords or topics.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content extends base {
    /**
     * List of modules that are considered activities.
     *
     * @return array
     */
    public const ACTIVITIES = [
        'mod_assign',
        'mod_data',
        'mod_feedback',
        'mod_forum',
        'mod_lesson',
        'mod_quiz',
        'mod_scorm',
        'mod_workshop',
    ];

    /** Solr highlight marker placed before a matched term. */
    private const HIGHLIGHT_START = '@@HI_S@@';

    /** Solr highlight marker placed after a matched term. */
    private const HIGHLIGHT_END = '@@HI_E@@';

    /**
     * Get the content based on the parameters.
     *
     * @return string The content to be displayed, based on the keywords or topics provided in the parameters.
     */
    #[\Override]
    public function get_content(): string {
        if (empty($this->params)) {
            return get_string('intent_content_default', 'local_parce');
        }

        // The params may come as an associative array like ["content" => ["kw1", "kw2"]].
        // Flatten to a simple list of keywords before building the search query.
        $keywords = $this->params['content'] ?? $this->params;
        if (!is_array($keywords)) {
            $keywords = [$keywords];
        }
        $q = implode(' ', $keywords);
        $content = self::get_search($q, $keywords);

        if (empty($content)) {
            $allowopenanswer = get_config('local_parce', 'allowopenanswer');
            if (!$allowopenanswer) {
                throw new \moodle_exception('intent_content_notfound', 'local_parce');
            }
            // If no content is found, and open answering is allowed, we can return an empty string or a default message.
            return '';
        }

        return $content;
    }

    /**
     * Content intent requiere the IA processing.
     *
     * @return bool True, indicating that IA processing is required for this intent.
     */
    #[\Override]
    public function require_ia(): bool {
        return true;
    }

    /**
     * Perform a search based on the provided keywords and resource types.
     *
     * @param string $search The search keywords.
     * @param array $keywords Keywords used to confirm that each result is relevant.
     * @param array $resourcetype Optional array of resource types to filter the search.
     * @return string JSON encoded string of found resources or an error message if search is unavailable
     */
    protected function get_search(string $search, array $keywords, array $resourcetype = []): string {
        global $USER;

        if (empty($search) && empty($resourcetype)) {
            return '';
        }

        $coursecontext = $this->context->get_course_context(false);

        $indexingenabled = \core_search\manager::is_indexing_enabled();

        if (!$indexingenabled) {
            return get_string('error_search_unavailable', 'local_parce');
        }

        $searchmanager = \core_search\manager::instance();

        $data = (object)['q' => $search];

        // Only restrict search to a specific course if it's not the site-level front page.
        // When chatting from the site level, search across all courses the user has access to.
        if (!empty($coursecontext) && $coursecontext->instanceid != SITEID) {
            $data->courseids = [$coursecontext->instanceid];
        }

        if (!empty($resourcetype)) {
            $data->areaids = [];
            $enabledsearchareas = \core_search\manager::get_search_areas_list(true);
            foreach ($enabledsearchareas as $area) {
                $componentname = $area->get_component_name();

                // A special case when all activities are requested.
                if (
                    in_array('mod', $resourcetype, true)
                    && strpos($componentname, 'mod_') === 0
                    && in_array($componentname, self::ACTIVITIES)
                ) {
                    $data->areaids[] = $area->get_area_id();
                    continue;
                }

                if (in_array($componentname, $resourcetype)) {
                    $data->areaids[] = $area->get_area_id();
                } else if (in_array($area->get_area_id(), $resourcetype)) {
                    // In case the area name is passed instead of the component name.
                    $data->areaids[] = $area->get_area_id();
                }
            }
        }

        // Impersonate the chat user so check_access() runs as them.
        // Do not set userids: engines that support it treat it as an author filter.
        $tmpuser = $USER;
        try {
            $results = [];
            if ($this->user->id != $USER->id) {
                $USER = $this->user;
            }
            $results = $searchmanager->search($data);

            if (empty($coursecontext)) {
                // Search in the public area.
                $USER = new \stdClass();
                $USER->id = 0;
                $results += $searchmanager->search($data);
            }
        } finally {
            $USER = $tmpuser;
        }

        $snippets = (new \local_parce\local\search\file_snippet_reader())->snippets_for($results, $search);

        $limit = 5;
        $coursenames = [];
        $found = [];
        $resultorder = 0;
        foreach ($results as $result) {
            // Solr omits empty stored fields. A file hit often has no activity content.
            if (!$result->is_set('areaid') || !$result->is_set('courseid')) {
                continue;
            }
            $parentid = $result->is_set('id') ? (string) $result->get('id') : '';
            $sourced = self::get_result_text($result, $snippets[$parentid] ?? '');

            // Some search engines treat unquoted terms as alternatives. Keep sufficiently relevant candidates,
            // then rank complete term matches before applying the result limit.
            $score = self::get_search_keyword_score($sourced['searchable'], $keywords);
            if ($score === 0) {
                continue;
            }

            $resource = new \stdClass();
            $resource->name = ($sourced['title'] !== '') ? $sourced['title'] : get_string('notitle', 'search');
            $resource->url = (string)$result->get_doc_url();
            $parts = \core_search\manager::extract_areaid_parts($result->get('areaid'));
            $resource->type = $parts[0];
            $resource->subtype = count($parts) > 1 ? $parts[1] : '';
            $resource->content = $sourced['content'];

            $courseid = $result->get('courseid');
            if (!isset($coursenames[$courseid])) {
                $course = get_course($courseid);
                $coursenames[$courseid] = $course->fullname;
            }
            $resource->coursename = $coursenames[$courseid];
            $resource->courseurl = (string)\course_get_url($courseid);

            $found[] = [
                'resource' => $resource,
                'score' => $score,
                'contentlength' => \core_text::strlen(trim((string) $resource->content)),
                'order' => $resultorder++,
            ];
        }

        if (empty($found)) {
            return '';
        }

        usort($found, static function (array $first, array $second): int {
            return ($second['score'] <=> $first['score'])
                ?: ($second['contentlength'] <=> $first['contentlength'])
                ?: ($first['order'] <=> $second['order']);
        });
        $found = array_map(static fn(array $candidate): \stdClass => $candidate['resource'], array_slice($found, 0, $limit));

        return \local_parce\local\controller::encode_retrieved_items($found);
    }

    /**
     * Read a returned search field without requiring it to be present.
     *
     * @param \core_search\document $result Search result.
     * @param string $field Field name.
     * @return string Field text without highlight markers, or an empty string when unset.
     */
    private static function get_result_field(\core_search\document $result, string $field): string {
        if (!$result->is_set($field)) {
            return '';
        }
        return self::strip_highlight_markers((string) $result->get($field));
    }

    /**
     * Remove Solr highlight markers from retrieved text.
     *
     * @param string $text Raw field or snippet.
     * @return string Text with markers removed.
     */
    private static function strip_highlight_markers(string $text): string {
        return str_replace([self::HIGHLIGHT_START, self::HIGHLIGHT_END], '', $text);
    }

    /**
     * Build the activity text and the text used to score a search result.
     *
     * Activity content comes first. Descriptions and the indexed file snippet follow.
     * File names stay in the scored text only, so a filename match is not treated as the body.
     *
     * @param \core_search\document $result Search result.
     * @param string $filesnippet Indexed file text fetched by Parce for this result.
     * @return array{title: string, content: string, searchable: string}
     */
    private static function get_result_text(\core_search\document $result, string $filesnippet = ''): array {
        $title = self::get_result_field($result, 'title');
        $parts = [];
        foreach (['content', 'description1', 'description2'] as $field) {
            $value = self::get_result_field($result, $field);
            if ($value !== '') {
                $parts[] = $value;
            }
        }
        $filesnippet = self::strip_highlight_markers(trim($filesnippet));
        if ($filesnippet !== '') {
            $parts[] = $filesnippet;
        }
        $content = implode("\n", $parts);

        $filenames = [];
        foreach ($result->get_files() as $file) {
            $name = trim($file->get_filename());
            if ($name !== '') {
                $filenames[] = $name;
            }
        }

        return [
            'title' => $title,
            'content' => $content,
            'searchable' => trim($title . ' ' . $content . ' ' . implode(' ', $filenames)),
        ];
    }

    /**
     * Score retrieved text by the number of planned terms it contains.
     *
     * @param string $text Retrieved title, content and descriptions.
     * @param array $keywords Search phrases supplied by the planner.
     * @return int Number of matching terms, or zero when the result is not sufficiently relevant.
     */
    private static function get_search_keyword_score(string $text, array $keywords): int {
        $terms = [];
        foreach ($keywords as $keyword) {
            $keyword = \core_text::strtolower(\core_text::specialtoascii(trim((string) $keyword)));
            foreach (preg_split('/[^\pL\pN]+/u', $keyword, -1, PREG_SPLIT_NO_EMPTY) as $term) {
                $terms[$term] = true;
            }
        }
        if (empty($terms)) {
            return 1;
        }

        $text = \core_text::strtolower(\core_text::specialtoascii($text));
        $matches = 0;
        foreach (array_keys($terms) as $term) {
            if (\core_text::strpos($text, $term) !== false) {
                $matches++;
            }
        }

        $required = count($terms) <= 2 ? count($terms) : (int) ceil(count($terms) / 2);
        return $matches >= $required ? $matches : 0;
    }

    /**
     * Format retrieved items as a deterministic Markdown list.
     *
     * @param string $contentjson JSON string containing retrieved items.
     * @param string $headingidentifier Language string used before the list.
     * @return string Markdown list, or an empty string when there are no valid items.
     */
    public static function format_search_results(string $contentjson, string $headingidentifier): string {
        $items = @json_decode($contentjson, true);
        if (empty($items) || !is_array($items)) {
            return '';
        }

        $links = [];
        foreach ($items as $item) {
            if (empty($item['name']) || empty($item['url']) || isset($links[$item['url']])) {
                continue;
            }
            $name = str_replace(['\\', '[', ']'], ['\\\\', '\\[', '\\]'], $item['name']);
            $links[$item['url']] = '- [' . $name . '](' . $item['url'] . ')';
        }
        if (empty($links)) {
            return '';
        }

        return get_string($headingidentifier, 'local_parce') . "\n\n" . implode("\n", $links);
    }

    /**
     * Check whether retrieved items only provide names and links, without explanatory content.
     *
     * @param string $contentjson JSON string containing retrieved items.
     * @return bool True when every item is linkable and has no content text.
     */
    public static function are_link_only_results(string $contentjson): bool {
        $items = @json_decode($contentjson, true);
        if (empty($items) || !is_array($items)) {
            return false;
        }

        foreach ($items as $item) {
            if (empty($item['name']) || empty($item['url']) || trim((string) ($item['content'] ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }
}
