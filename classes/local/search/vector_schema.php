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
 * Prepares and checks the Solr fields that store passage vectors.
 *
 * The fields belong to Parce. Install and upgrade do not call Solr. An administrator
 * prepares the schema from the vector search page. Preparing does not embed text.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class vector_schema {
    /** Dense vector field type owned by Parce. */
    public const FIELD_TYPE = 'parce_knn_384';

    /** Vector of one passage. */
    public const VECTOR_FIELD = 'parce_content_vector';

    /** Passage text returned to the answer model. It is stored and not indexed. */
    public const CHUNK_FIELD = 'parce_chunk_text';

    /** Activity document id that the passage belongs to. */
    public const PARENT_FIELD = 'parce_parent_id';

    /** Hash of the source text that produced the passage. */
    public const SOURCE_FIELD = 'parce_vector_source';

    /** Solr id of the activity or file document that was split. */
    public const ORIGIN_FIELD = 'parce_origin_id';

    /** Identifier prefix of passage documents. */
    public const ID_PREFIX = 'parce-vector-';

    /** Embedding model the worker must load. */
    public const MODEL = 'paraphrase-multilingual-MiniLM-L12-v2';

    /** Vector size of that model. */
    public const DIMENSION = 384;

    /** Tokens in one passage. */
    public const CHUNK_TOKENS = 120;

    /** Tokens shared by neighbouring passages. */
    public const OVERLAP_TOKENS = 20;

    /** Initial cosine accepted by a passage. */
    public const DEFAULT_MINIMUM = 0.55;

    /**
     * Whether global search is Solr and the server settings needed to call it are present.
     *
     * @return bool
     */
    public static function is_solr_configured(): bool {
        return self::core_url() !== null;
    }

    /**
     * Configured cosine minimum, kept inside 0 and 1.
     *
     * @return float
     */
    public static function minimum_score(): float {
        $value = get_config('local_parce', 'vector_min_score');
        if (!is_numeric($value)) {
            return self::DEFAULT_MINIMUM;
        }
        $value = (float) $value;
        if ($value < 0 || $value > 1) {
            return self::DEFAULT_MINIMUM;
        }
        return $value;
    }

    /**
     * Schema command that creates the dense vector type.
     *
     * @return array
     */
    public static function add_field_type_command(): array {
        return ['add-field-type' => self::field_type_attributes()];
    }

    /**
     * Attributes of the dense vector type.
     *
     * @return array
     */
    public static function field_type_attributes(): array {
        return [
            'name' => self::FIELD_TYPE,
            'class' => 'solr.DenseVectorField',
            'vectorDimension' => self::DIMENSION,
            'similarityFunction' => 'cosine',
        ];
    }

    /**
     * Schema commands that create the passage fields.
     *
     * @return array<int, array>
     */
    public static function add_field_commands(): array {
        $commands = [];
        foreach (self::field_attributes() as $attributes) {
            $commands[] = ['add-field' => $attributes];
        }
        return $commands;
    }

    /**
     * Attributes of every passage field, keyed by name.
     *
     * @return array<string, array>
     */
    public static function field_attributes(): array {
        return [
            self::VECTOR_FIELD => [
                'name' => self::VECTOR_FIELD,
                'type' => self::FIELD_TYPE,
                'stored' => true,
                'indexed' => true,
                'multiValued' => false,
            ],
            self::CHUNK_FIELD => [
                'name' => self::CHUNK_FIELD,
                'type' => 'string',
                'stored' => true,
                'indexed' => false,
                'multiValued' => false,
            ],
            self::PARENT_FIELD => [
                'name' => self::PARENT_FIELD,
                'type' => 'string',
                'stored' => true,
                'indexed' => true,
                'multiValued' => false,
            ],
            self::SOURCE_FIELD => [
                'name' => self::SOURCE_FIELD,
                'type' => 'string',
                'stored' => true,
                'indexed' => true,
                'multiValued' => false,
            ],
            self::ORIGIN_FIELD => [
                'name' => self::ORIGIN_FIELD,
                'type' => 'string',
                'stored' => true,
                'indexed' => true,
                'multiValued' => false,
            ],
        ];
    }

    /**
     * Read a field or field-type object from a Solr schema response.
     *
     * @param array $response Decoded schema response.
     * @param string $key field or fieldType.
     * @return array|null
     */
    public static function read_definition(array $response, string $key): ?array {
        if (!isset($response[$key]) || !is_array($response[$key])) {
            return null;
        }
        return $response[$key];
    }

    /**
     * Whether the vector type exists with the expected dimension and similarity.
     *
     * @param array|null $fieldtype Field type from Solr.
     * @return bool
     */
    public static function field_type_is_ready(?array $fieldtype): bool {
        if ($fieldtype === null) {
            return false;
        }
        $class = (string) ($fieldtype['class'] ?? '');
        $dimension = $fieldtype['vectorDimension'] ?? null;
        $similarity = strtolower((string) ($fieldtype['similarityFunction'] ?? ''));
        return ($fieldtype['name'] ?? '') === self::FIELD_TYPE
            && str_ends_with($class, 'DenseVectorField')
            && (int) $dimension === self::DIMENSION
            && $similarity === 'cosine';
    }

    /**
     * Whether one passage field matches the definition Parce expects.
     *
     * @param array|null $field Field definition from Solr.
     * @param string $name Expected field name.
     * @return bool
     */
    public static function field_is_ready(?array $field, string $name): bool {
        $expected = self::field_attributes()[$name] ?? null;
        if ($field === null || $expected === null) {
            return false;
        }
        return ($field['name'] ?? '') === $expected['name']
            && ($field['type'] ?? '') === $expected['type']
            && array_key_exists('stored', $field)
            && array_key_exists('indexed', $field)
            && array_key_exists('multiValued', $field)
            && self::is_true($field['stored']) === $expected['stored']
            && self::is_true($field['indexed']) === $expected['indexed']
            && self::is_true($field['multiValued']) === $expected['multiValued'];
    }

    /**
     * Build the check result from schema data already fetched.
     *
     * @param array|null $fieldtype Vector type, or null when it is absent.
     * @param array<string, array|null> $fields Field definitions keyed by name.
     * @param int|null $documentcount Passage documents, or null when the count was not run.
     * @param bool $filecontent Whether the optional stored file field exists.
     * @param string $error Empty, a known code, or a Solr message.
     * @return array
     */
    public static function summarise(
        ?array $fieldtype,
        array $fields,
        ?int $documentcount,
        bool $filecontent,
        string $error = ''
    ): array {
        $fieldsok = self::field_type_is_ready($fieldtype);
        foreach (array_keys(self::field_attributes()) as $name) {
            if (!self::field_is_ready($fields[$name] ?? null, $name)) {
                $fieldsok = false;
            }
        }
        return [
            'reachable' => true,
            'prepared' => false,
            'typeok' => self::field_type_is_ready($fieldtype),
            'dimensionmismatch' => $fieldtype !== null && !self::field_type_is_ready($fieldtype),
            'fieldsok' => $fieldsok,
            'documentcount' => $documentcount,
            'filecontent' => $filecontent,
            'error' => $error,
        ];
    }

    /**
     * Turn a schema check result into notifications.
     *
     * @param array $status Result from check() or prepare().
     * @return array<int, array{type: string, message: string}>
     */
    public static function notices(array $status): array {
        $notices = [];
        if (!empty($status['prepared'])) {
            $notices[] = [
                'type' => 'success',
                'message' => get_string('vectorsearch_prepared', 'local_parce'),
            ];
        }

        $error = (string) ($status['error'] ?? '');
        if ($error === 'notconfigured') {
            $notices[] = [
                'type' => 'warning',
                'message' => get_string('vectorsearch_solr_required', 'local_parce'),
            ];
            return $notices;
        }
        if ($error === 'unreachable') {
            $notices[] = [
                'type' => 'error',
                'message' => get_string('vectorsearch_unreachable', 'local_parce'),
            ];
            return $notices;
        }
        if ($error === 'dimensionmismatch' || !empty($status['dimensionmismatch'])) {
            $notices[] = [
                'type' => 'error',
                'message' => get_string('vectorsearch_dimension_mismatch', 'local_parce'),
            ];
            return $notices;
        }
        if ($error !== '') {
            $notices[] = [
                'type' => 'error',
                'message' => get_string('vectorsearch_error', 'local_parce', s($error)),
            ];
        }

        $notices[] = [
            'type' => !empty($status['typeok']) ? 'success' : 'warning',
            'message' => get_string(
                !empty($status['typeok']) ? 'vectorsearch_type_ok' : 'vectorsearch_type_missing',
                'local_parce'
            ),
        ];
        $notices[] = [
            'type' => !empty($status['fieldsok']) ? 'success' : 'warning',
            'message' => get_string(
                !empty($status['fieldsok']) ? 'vectorsearch_fields_ok' : 'vectorsearch_fields_missing',
                'local_parce'
            ),
        ];
        if (!empty($status['filecontent'])) {
            $notices[] = [
                'type' => 'info',
                'message' => get_string('vectorsearch_filecontent_present', 'local_parce'),
            ];
        } else {
            $notices[] = [
                'type' => 'info',
                'message' => get_string('vectorsearch_filecontent_absent', 'local_parce'),
            ];
        }
        if (array_key_exists('documentcount', $status) && $status['documentcount'] !== null) {
            $count = (int) $status['documentcount'];
            $notices[] = [
                'type' => $count > 0 ? 'success' : 'info',
                'message' => $count > 0
                    ? get_string('vectorsearch_documents_ready', 'local_parce', $count)
                    : get_string('vectorsearch_documents_empty', 'local_parce'),
            ];
        }
        return $notices;
    }

    /**
     * Read an error message from a schema response.
     *
     * @param array $response Request result with code and data.
     * @return string
     */
    public static function error_from_response(array $response): string {
        return file_content_schema::error_from_response($response);
    }

    /**
     * Check the vector type, the passage fields and how many passages exist.
     *
     * @return array Check result. This method does not change the schema.
     */
    public function check(): array {
        if (!self::is_solr_configured()) {
            return self::empty_status('notconfigured');
        }

        $type = $this->definition('/schema/fieldtypes/' . rawurlencode(self::FIELD_TYPE), 'fieldType');
        if ($type === false) {
            return self::empty_status('unreachable');
        }
        if (is_string($type)) {
            return self::empty_status($type);
        }

        $fields = [];
        foreach (array_keys(self::field_attributes()) as $name) {
            $field = $this->definition('/schema/fields/' . rawurlencode($name), 'field');
            if ($field === false) {
                return self::empty_status('unreachable');
            }
            if (is_string($field)) {
                return self::empty_status($field);
            }
            $fields[$name] = $field;
        }

        $filefield = $this->definition(
            '/schema/fields/' . rawurlencode(file_content_schema::FIELD_NAME),
            'field'
        );
        if ($filefield === false) {
            return self::empty_status('unreachable');
        }

        $count = null;
        if (self::field_type_is_ready($type) && self::summarise($type, $fields, null, false)['fieldsok']) {
            $select = $this->request('GET', '/select', null, [
                'q' => '*:*',
                'fq' => 'id:' . self::ID_PREFIX . '*',
                'rows' => 0,
                'wt' => 'json',
            ]);
            if ($select !== null && $select['code'] === 200) {
                $count = (int) ($select['data']['response']['numFound'] ?? 0);
            }
        }

        return self::summarise($type, $fields, $count, is_array($filefield));
    }

    /**
     * Create the vector type and passage fields, then check again.
     *
     * A type that already exists with another dimension is left unchanged.
     *
     * @return array Check result, with prepared true when the schema write succeeded.
     */
    public function prepare(): array {
        if (!self::is_solr_configured()) {
            return self::empty_status('notconfigured');
        }

        $type = $this->definition('/schema/fieldtypes/' . rawurlencode(self::FIELD_TYPE), 'fieldType');
        if ($type === false) {
            return self::empty_status('unreachable');
        }
        if (is_string($type)) {
            return self::empty_status($type);
        }
        if ($type !== null && !self::field_type_is_ready($type)) {
            return self::empty_status('dimensionmismatch');
        }
        if ($type === null && !$this->write(self::add_field_type_command())) {
            $status = $this->check();
            $status['prepared'] = false;
            return $status;
        }

        foreach (self::field_attributes() as $name => $attributes) {
            $field = $this->definition('/schema/fields/' . rawurlencode($name), 'field');
            if ($field === false) {
                return self::empty_status('unreachable');
            }
            if (is_string($field)) {
                return self::empty_status($field);
            }
            if ($field === null) {
                $command = ['add-field' => $attributes];
            } else if (!self::field_is_ready($field, $name)) {
                $command = ['replace-field' => $attributes];
            } else {
                continue;
            }
            if (!$this->write($command)) {
                $status = $this->check();
                $status['prepared'] = false;
                return $status;
            }
        }

        $status = $this->check();
        $status['prepared'] = $status['error'] === '' && !empty($status['fieldsok']);
        return $status;
    }

    /**
     * Select parameters that count passage documents.
     *
     * @return array
     */
    public static function sample_query(): array {
        return [
            'q' => '*:*',
            'fq' => 'id:' . self::ID_PREFIX . '*',
            'rows' => 0,
            'wt' => 'json',
        ];
    }

    /**
     * Fetch a schema definition.
     *
     * @param string $path Schema path.
     * @param string $key Response key, field or fieldType.
     * @return array|null|false|string Definition, null when absent, false when unreachable, or an error.
     */
    private function definition(string $path, string $key): array|null|false|string {
        $response = $this->request('GET', $path);
        if ($response === null) {
            return false;
        }
        if ($response['code'] === 404) {
            return null;
        }
        if ($response['code'] !== 200) {
            return self::error_from_response($response);
        }
        return self::read_definition($response['data'], $key);
    }

    /**
     * Post one schema command.
     *
     * @param array $command Schema command.
     * @return bool True when Solr accepted the command.
     */
    private function write(array $command): bool {
        $posted = $this->request('POST', '/schema', $command);
        if ($posted === null || (int) ($posted['code'] ?? 0) !== 200) {
            return false;
        }
        $data = $posted['data'] ?? [];
        return empty($data['error']) && empty($data['errors']);
    }

    /**
     * Base URL of the configured Solr core, without a handler path.
     *
     * @return string|null
     */
    private static function core_url(): ?string {
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
        return $url . '/solr/' . $config->indexname;
    }

    /**
     * Status used when Solr cannot be inspected.
     *
     * @param string $error notconfigured, unreachable or dimensionmismatch.
     * @return array
     */
    private static function empty_status(string $error): array {
        $status = self::summarise(null, [], null, false, $error);
        $status['reachable'] = false;
        if ($error === 'dimensionmismatch') {
            $status['dimensionmismatch'] = true;
            $status['reachable'] = true;
        }
        return $status;
    }

    /**
     * Call one Solr handler on the configured core.
     *
     * @param string $method GET or POST.
     * @param string $path Handler path, beginning with a slash.
     * @param array|null $command JSON object for POST, or null.
     * @param array $query Query parameters for GET.
     * @return array|null Code and decoded body, or null when the server cannot be reached.
     */
    private function request(string $method, string $path, ?array $command = null, array $query = []): ?array {
        $base = self::core_url();
        if ($base === null) {
            return null;
        }
        $config = get_config('search_solr');
        $curl = new \curl(['ignoresecurity' => true]);
        $timeout = !empty($config->server_timeout) ? (int) $config->server_timeout : 30;
        $curl->setopt(['CURLOPT_TIMEOUT' => $timeout]);
        if (!empty($config->server_username) && !empty($config->server_password)) {
            $authorization = $config->server_username . ':' . $config->server_password;
            $curl->setHeader('Authorization: Basic ' . base64_encode($authorization));
        }

        if ($method === 'POST') {
            $curl->setHeader('Content-type: application/json');
            $response = $curl->post($base . $path, json_encode($command ?? []));
        } else {
            $response = $curl->get($base . $path, $query);
        }

        $info = $curl->get_info();
        $code = (int) ($info['http_code'] ?? 0);
        if (!is_string($response) || $code === 0) {
            return null;
        }
        $data = json_decode($response, true);
        return [
            'code' => $code,
            'data' => is_array($data) ? $data : [],
        ];
    }

    /**
     * Normalise a Solr boolean that may arrive as a real boolean or a string.
     *
     * @param mixed $value Schema attribute.
     * @return bool
     */
    private static function is_true($value): bool {
        return $value === true || $value === 'true' || $value === 1 || $value === '1';
    }
}
