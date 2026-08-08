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

namespace local_parce\local\intent;

/**
 * Resolve explicit requests to find or access Moodle resources without a second AI call.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class resource extends content {
    /**
     * Resource searches are returned directly from Moodle Search.
     *
     * @return bool False because no answer-generation call is required.
     */
    #[\Override]
    public function require_ia(): bool {
        return false;
    }

    /**
     * Search and format matching Moodle resources.
     *
     * @return string Markdown links to matching resources.
     */
    #[\Override]
    public function get_content(): string {
        $keywords = $this->params['content'] ?? [];
        if (!is_array($keywords)) {
            $keywords = [$keywords];
        }
        $resourcetypes = $this->params['resourcetype'] ?? [];
        if (!is_array($resourcetypes)) {
            $resourcetypes = [$resourcetypes];
        }

        $keywords = $this->remove_type_keywords($keywords);
        if (empty($keywords)) {
            throw new \moodle_exception('intent_resource_notfound', 'local_parce');
        }

        $results = $this->get_search(implode(' ', $keywords), $keywords, $resourcetypes);
        $response = self::format_search_results($results, 'resource_results');
        if ($response === '') {
            throw new \moodle_exception('intent_resource_notfound', 'local_parce');
        }

        return $response;
    }

    /**
     * Remove words that only identify an explicitly supplied resource type.
     *
     * @param array $keywords Search terms from the planner.
     * @return array Distinctive search terms.
     */
    private function remove_type_keywords(array $keywords): array {
        $generic = [
            'course', 'courses', 'curso', 'cursos',
            'activity', 'activities', 'actividad', 'actividades',
            'resource', 'resources', 'recurso', 'recursos',
        ];

        return array_values(array_filter($keywords, static function ($keyword) use ($generic): bool {
            $keyword = trim((string) $keyword);
            return $keyword !== '' && !in_array(\core_text::strtolower($keyword), $generic, true);
        }));
    }
}
