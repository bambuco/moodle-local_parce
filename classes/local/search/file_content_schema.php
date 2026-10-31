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
 * Prepares and checks the stored Solr copy of indexed file text.
 *
 * Moodle indexes file text in solr_filecontent and does not store it, so highlighting
 * cannot return a snippet. This class adds a stored field that Parce owns. It runs only
 * when an administrator asks; install and upgrade do not call Solr.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class file_content_schema {
    /** Stored field that receives a copy of indexed file text. */
    public const FIELD_NAME = 'parce_filecontent';

    /** Moodle field that holds indexed, unstored file text. */
    public const SOURCE_FIELD = 'solr_filecontent';

    /**
     * Whether global search is Solr and the server settings needed to call it are present.
     *
     * @return bool
     */
    public static function is_solr_configured(): bool {
        return self::core_url() !== null;
    }

    /**
     * Whether Moodle is configured to send files to Solr.
     *
     * @return bool
     */
    public static function file_indexing_enabled(): bool {
        return !empty(get_config('search_solr', 'fileindexing'));
    }

    /**
     * Attributes of the stored file-text field.
     *
     * @return array
     */
    public static function field_attributes(): array {
        return [
            'name' => self::FIELD_NAME,
            'type' => 'text_general',
            'stored' => true,
            'indexed' => true,
            'multiValued' => false,
        ];
    }

    /**
     * Schema command that creates the stored field.
     *
     * @return array
     */
    public static function add_field_command(): array {
        return ['add-field' => self::field_attributes()];
    }

    /**
     * Schema command that replaces the stored field when its definition does not match.
     *
     * @return array
     */
    public static function replace_field_command(): array {
        return ['replace-field' => self::field_attributes()];
    }

    /**
     * Schema command that copies indexed file text into the stored field.
     *
     * @return array
     */
    public static function add_copy_field_command(): array {
        return ['add-copy-field' => [
            'source' => self::SOURCE_FIELD,
            'dest' => self::FIELD_NAME,
        ]];
    }

    /**
     * Read the field object from a Solr schema response.
     *
     * @param array $response Decoded schema field response.
     * @return array|null Field definition, or null when the response has none.
     */
    public static function read_field(array $response): ?array {
        if (!isset($response['field']) || !is_array($response['field'])) {
            return null;
        }
        return $response['field'];
    }

    /**
     * Read copy-field rules from a Solr schema response.
     *
     * @param array $response Decoded copy-field response.
     * @return array
     */
    public static function read_copy_fields(array $response): array {
        $copies = $response['copyFields'] ?? [];
        return is_array($copies) ? $copies : [];
    }

    /**
     * Whether the stored field exists with the definition Parce expects.
     *
     * @param array|null $field Field definition from Solr.
     * @return bool
     */
    public static function field_is_ready(?array $field): bool {
        if ($field === null) {
            return false;
        }
        return ($field['name'] ?? '') === self::FIELD_NAME
            && ($field['type'] ?? '') === 'text_general'
            && array_key_exists('stored', $field)
            && array_key_exists('indexed', $field)
            && array_key_exists('multiValued', $field)
            && self::is_true($field['stored'])
            && self::is_true($field['indexed'])
            && !self::is_true($field['multiValued']);
    }

    /**
     * Whether a copy rule already sends file text to the stored field.
     *
     * @param array $copyfields Copy-field rules.
     * @return bool
     */
    public static function copy_field_exists(array $copyfields): bool {
        foreach ($copyfields as $copy) {
            if (!is_array($copy) || ($copy['source'] ?? '') !== self::SOURCE_FIELD) {
                continue;
            }
            $destinations = $copy['dest'] ?? '';
            if (!is_array($destinations)) {
                $destinations = [$destinations];
            }
            if (in_array(self::FIELD_NAME, $destinations, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Build the check result from schema and select data already fetched.
     *
     * @param bool $fileindexing Whether Moodle file indexing is enabled.
     * @param array|null $field Stored field definition, or null when it is absent.
     * @param array $copyfields Copy-field rules.
     * @param array|null $select Decoded select response, or null when it was not run.
     * @param string $error Empty, a known code, or a Solr message.
     * @return array
     */
    public static function summarise(
        bool $fileindexing,
        ?array $field,
        array $copyfields,
        ?array $select,
        string $error = ''
    ): array {
        $count = null;
        $hastext = false;
        if (is_array($select)) {
            $count = (int) ($select['response']['numFound'] ?? 0);
            $document = $select['response']['docs'][0] ?? null;
            $text = is_array($document) ? trim((string) ($document[self::FIELD_NAME] ?? '')) : '';
            $hastext = $text !== '';
        }

        return [
            'reachable' => true,
            'prepared' => false,
            'fieldok' => self::field_is_ready($field),
            'copyfieldok' => self::copy_field_exists($copyfields),
            'fileindexing' => $fileindexing,
            'documentcount' => $count,
            'hasstoredtext' => $hastext,
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
                'message' => get_string('filecontent_prepared', 'local_parce'),
            ];
        }

        $error = (string) ($status['error'] ?? '');
        if ($error === 'notconfigured') {
            $notices[] = [
                'type' => 'warning',
                'message' => get_string('filecontent_solr_required', 'local_parce'),
            ];
            return $notices;
        }
        if ($error === 'unreachable') {
            $notices[] = [
                'type' => 'error',
                'message' => get_string('filecontent_unreachable', 'local_parce'),
            ];
            return $notices;
        }
        if ($error !== '') {
            $notices[] = [
                'type' => 'error',
                'message' => get_string('filecontent_error', 'local_parce', s($error)),
            ];
        }
        if (empty($status['reachable'])) {
            return $notices;
        }

        $notices[] = [
            'type' => empty($status['fieldok']) ? 'warning' : 'success',
            'message' => get_string(
                empty($status['fieldok']) ? 'filecontent_field_missing' : 'filecontent_field_ok',
                'local_parce'
            ),
        ];
        $notices[] = [
            'type' => empty($status['copyfieldok']) ? 'warning' : 'success',
            'message' => get_string(
                empty($status['copyfieldok']) ? 'filecontent_copy_missing' : 'filecontent_copy_ok',
                'local_parce'
            ),
        ];
        if (empty($status['fileindexing'])) {
            $notices[] = [
                'type' => 'warning',
                'message' => get_string('filecontent_fileindexing_off', 'local_parce'),
            ];
        }
        if (!empty($status['fieldok']) && !empty($status['copyfieldok'])) {
            if (!empty($status['hasstoredtext'])) {
                $notices[] = [
                    'type' => 'success',
                    'message' => get_string('filecontent_documents_ready', 'local_parce', (int) $status['documentcount']),
                ];
            } else if ($status['documentcount'] === 0) {
                $notices[] = [
                    'type' => 'info',
                    'message' => get_string('filecontent_documents_empty', 'local_parce'),
                ];
            } else {
                $notices[] = [
                    'type' => 'warning',
                    'message' => get_string('filecontent_documents_unknown', 'local_parce'),
                ];
            }
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
        $data = $response['data'] ?? [];
        if (isset($data['error']['msg']) && is_string($data['error']['msg'])) {
            return $data['error']['msg'];
        }
        if (!empty($data['errors']) && is_array($data['errors'])) {
            $messages = [];
            foreach ($data['errors'] as $error) {
                if (!is_array($error) || empty($error['errorMessages']) || !is_array($error['errorMessages'])) {
                    continue;
                }
                foreach ($error['errorMessages'] as $message) {
                    $messages[] = (string) $message;
                }
            }
            if ($messages !== []) {
                return implode(' ', $messages);
            }
        }
        $code = (int) ($response['code'] ?? 0);
        return $code > 0 ? 'HTTP ' . $code : 'unreachable';
    }

    /**
     * Check the stored field, the copy rule and whether any document already has text.
     *
     * @return array Check result. This method does not change the schema.
     */
    public function check(): array {
        if (!self::is_solr_configured()) {
            return self::empty_status('notconfigured');
        }

        $fieldresponse = $this->request('GET', '/schema/fields/' . rawurlencode(self::FIELD_NAME));
        if ($fieldresponse === null) {
            return self::empty_status('unreachable');
        }
        if (!in_array($fieldresponse['code'], [200, 404], true)) {
            return self::empty_status(self::error_from_response($fieldresponse));
        }

        $copyresponse = $this->request('GET', '/schema/copyfields');
        if ($copyresponse === null) {
            return self::empty_status('unreachable');
        }
        if ($copyresponse['code'] !== 200) {
            return self::empty_status(self::error_from_response($copyresponse));
        }

        $field = self::read_field($fieldresponse['data']);
        $select = null;
        if (self::field_is_ready($field)) {
            $selectresponse = $this->request('GET', '/select', null, self::sample_query());
            if ($selectresponse !== null && $selectresponse['code'] === 200) {
                $select = $selectresponse['data'];
            }
        }

        return self::summarise(
            self::file_indexing_enabled(),
            $field,
            self::read_copy_fields($copyresponse['data']),
            $select
        );
    }

    /**
     * Create or repair the stored field and its copy rule, then check again.
     *
     * @return array Check result, with prepared true when the schema write succeeded.
     */
    public function prepare(): array {
        if (!self::is_solr_configured()) {
            return self::empty_status('notconfigured');
        }

        $fieldresponse = $this->request('GET', '/schema/fields/' . rawurlencode(self::FIELD_NAME));
        if ($fieldresponse === null) {
            return self::empty_status('unreachable');
        }
        if (!in_array($fieldresponse['code'], [200, 404], true)) {
            return self::empty_status(self::error_from_response($fieldresponse));
        }

        $commands = [];
        $field = self::read_field($fieldresponse['data']);
        if ($field === null) {
            $commands[] = self::add_field_command();
        } else if (!self::field_is_ready($field)) {
            $commands[] = self::replace_field_command();
        }

        $copyresponse = $this->request('GET', '/schema/copyfields');
        if ($copyresponse === null) {
            return self::empty_status('unreachable');
        }
        if ($copyresponse['code'] !== 200) {
            return self::empty_status(self::error_from_response($copyresponse));
        }
        if (!self::copy_field_exists(self::read_copy_fields($copyresponse['data']))) {
            $commands[] = self::add_copy_field_command();
        }

        foreach ($commands as $command) {
            $posted = $this->request('POST', '/schema', $command);
            if ($posted === null) {
                $status = self::empty_status('unreachable');
                $status['prepared'] = false;
                return $status;
            }
            if (!self::schema_write_ok($posted)) {
                $status = $this->check();
                $status['error'] = self::error_from_response($posted);
                $status['prepared'] = false;
                return $status;
            }
        }

        $status = $this->check();
        $status['prepared'] = $status['error'] === '';
        return $status;
    }

    /**
     * Select parameters that look for one document with stored file text.
     *
     * @return array
     */
    public static function sample_query(): array {
        return [
            'q' => '*:*',
            'fq' => self::FIELD_NAME . ':*',
            'fl' => 'id,' . self::FIELD_NAME,
            'rows' => 1,
            'wt' => 'json',
        ];
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
     * @param string $error notconfigured or unreachable.
     * @return array
     */
    private static function empty_status(string $error): array {
        return [
            'reachable' => false,
            'prepared' => false,
            'fieldok' => false,
            'copyfieldok' => false,
            'fileindexing' => self::file_indexing_enabled(),
            'documentcount' => null,
            'hasstoredtext' => false,
            'error' => $error,
        ];
    }

    /**
     * Whether a schema write response reports success.
     *
     * @param array $response Request result with code and data.
     * @return bool
     */
    private static function schema_write_ok(array $response): bool {
        if ((int) ($response['code'] ?? 0) !== 200) {
            return false;
        }
        $data = $response['data'] ?? [];
        return empty($data['error']) && empty($data['errors']);
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
