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
 * Reads indexed file text for search hits Parce already received.
 *
 * File text is indexed in Solr and not stored, so the search API does not return it.
 * When file content is enabled, this reader asks Solr for a highlight of the stored
 * copy field on the file documents already returned. The user phrase is not a
 * document query.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class file_snippet_reader {
    /** Highlight fragment size, in characters. */
    public const FRAG_SIZE = 510;

    /** Marker Solr places before a highlighted term. */
    public const HIGHLIGHT_START = '@@HI_S@@';

    /** Marker Solr places after a highlighted term. */
    public const HIGHLIGHT_END = '@@HI_E@@';

    /**
     * Build the Solr id of a file document from its parent search document.
     *
     * @param string $parentid Search document id.
     * @param int $fileid Stored file id.
     * @return string File document id.
     */
    public static function file_document_id(string $parentid, int $fileid): string {
        return $parentid . '-solrfile' . $fileid;
    }

    /**
     * Build a select request that returns only the given file documents.
     *
     * @param string[] $ids File document ids.
     * @param string $highlightquery Phrase used only to place the snippet.
     * @return array Solr select parameters.
     */
    public static function select_params(array $ids, string $highlightquery): array {
        $quoted = [];
        foreach ($ids as $id) {
            $id = (string) $id;
            if ($id === '') {
                continue;
            }
            $quoted[] = '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $id) . '"';
        }
        $params = [
            'q' => 'id:(' . implode(' OR ', $quoted) . ')',
            'wt' => 'json',
            'rows' => count($quoted),
            'fl' => 'id',
            'hl' => 'true',
            'hl.fl' => file_content_schema::FIELD_NAME,
            'hl.fragsize' => self::FRAG_SIZE,
            'hl.simple.pre' => self::HIGHLIGHT_START,
            'hl.simple.post' => self::HIGHLIGHT_END,
        ];
        $highlightquery = trim($highlightquery);
        if ($highlightquery !== '') {
            $params['hl.q'] = $highlightquery;
        }
        return $params;
    }

    /**
     * Group file highlights by the parent search document that owned each file.
     *
     * @param array $highlighting Highlighting section of a Solr JSON response.
     * @param array $filetoparent File document id mapped to parent document id.
     * @return array Parent document id mapped to combined snippet text.
     */
    public static function snippets_by_parent(array $highlighting, array $filetoparent): array {
        $grouped = [];
        foreach ($highlighting as $fileid => $fields) {
            $fileid = (string) $fileid;
            if (!isset($filetoparent[$fileid]) || !is_array($fields)) {
                continue;
            }
            $snippet = self::first_snippet($fields[file_content_schema::FIELD_NAME] ?? null);
            if ($snippet === '') {
                continue;
            }
            $grouped[$filetoparent[$fileid]][] = $snippet;
        }

        $snippets = [];
        foreach ($grouped as $parentid => $parts) {
            $snippets[$parentid] = implode("\n", $parts);
        }
        return $snippets;
    }

    /**
     * Fetch file snippets for documents the search API already returned.
     *
     * @param \core_search\document[] $results Search results.
     * @param string $highlightquery Phrase used only to place each snippet.
     * @return array Parent document id mapped to snippet text. Empty when file content is disabled,
     *         Solr is not in use, or the request fails.
     */
    public function snippets_for(array $results, string $highlightquery): array {
        if (!self::is_enabled()) {
            return [];
        }
        try {
            $filetoparent = self::file_ids_for_results($results);
            if ($filetoparent === []) {
                return [];
            }
            $body = $this->request(array_keys($filetoparent), $highlightquery);
            if ($body === null) {
                return [];
            }
            $decoded = json_decode($body, true);
            if (!is_array($decoded) || !is_array($decoded['highlighting'] ?? null)) {
                return [];
            }
            return self::snippets_by_parent($decoded['highlighting'], $filetoparent);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Whether this site has chosen to read stored file text while answering.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return !empty(get_config('local_parce', 'filecontent'));
    }

    /**
     * Map each attached file to the search document that returned it.
     *
     * @param \core_search\document[] $results Search results.
     * @return array File document id mapped to parent document id.
     */
    private static function file_ids_for_results(array $results): array {
        $filetoparent = [];
        foreach ($results as $result) {
            if (!$result instanceof \core_search\document || !$result->is_set('id')) {
                continue;
            }
            $parentid = (string) $result->get('id');
            foreach ($result->get_files() as $file) {
                $filetoparent[self::file_document_id($parentid, (int) $file->get_id())] = $parentid;
            }
        }
        return $filetoparent;
    }

    /**
     * Request highlights for known file ids.
     *
     * @param string[] $ids File document ids.
     * @param string $highlightquery Phrase used only to place the snippet.
     * @return string|null JSON body, or null when the request cannot be made or fails.
     */
    private function request(array $ids, string $highlightquery): ?string {
        global $CFG;

        if (($CFG->searchengine ?? '') !== 'solr') {
            return null;
        }
        $config = get_config('search_solr');
        if (!is_object($config) || empty($config->server_hostname) || empty($config->indexname)) {
            return null;
        }

        $protocol = !empty($config->secure) ? 'https' : 'http';
        $url = $protocol . '://' . rtrim($config->server_hostname, '/');
        if (!empty($config->server_port)) {
            $url .= ':' . $config->server_port;
        }
        $url .= '/solr/' . $config->indexname . '/select';

        $curl = new \curl(['ignoresecurity' => true]);
        $timeout = !empty($config->server_timeout) ? (int) $config->server_timeout : 30;
        $curl->setopt(['CURLOPT_TIMEOUT' => $timeout]);
        if (!empty($config->server_username) && !empty($config->server_password)) {
            $authorization = $config->server_username . ':' . $config->server_password;
            $curl->setHeader('Authorization: Basic ' . base64_encode($authorization));
        }

        $response = $curl->get($url, self::select_params($ids, $highlightquery));
        $info = $curl->get_info();
        if (!is_string($response) || $response === '' || (int) ($info['http_code'] ?? 0) !== 200) {
            return null;
        }
        return $response;
    }

    /**
     * Read the first highlight fragment.
     *
     * @param mixed $value Highlight field value.
     * @return string Fragment, or an empty string.
     */
    private static function first_snippet($value): string {
        if (is_array($value)) {
            $value = reset($value);
        }
        return is_string($value) ? $value : '';
    }
}
