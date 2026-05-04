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
        $content = self::get_search($q);

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
     * @param array $resourcetype Optional array of resource types to filter the search.
     * @return string JSON encoded string of found resources or an error message if search is unavailable
     */
    private function get_search(string $search, array $resourcetype = []): string {
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
                    $resourcetype == 'mod'
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

        // The logic for "$data->userids" is included but was not found to be implemented in the core code.
        $data->userids = [$this->user->id];

        // ToDo: A horrible hack to replace the unimplemented "userids" parameter.
        // A temporary impersonation of the user is needed because the Search API does
        // not take into account the user being filtered with.
        $tmpuser = clone($USER);
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
        } catch (\Exception $e) {
            // Restore the original user before throwing the exception.
            $USER = $tmpuser;
            throw $e;
        }

        // Restore the original user.
        $USER = $tmpuser;

        $k = 0;
        $limit = 5;
        $coursenames = [];
        foreach ($results as $result) {
            $title = $result->get('title');

            $resource = new \stdClass();
            $resource->name = ($title !== '') ? $title : get_string('notitle', 'search');
            $resource->url = (string)$result->get_doc_url();
            $parts = \core_search\manager::extract_areaid_parts($result->get('areaid'));
            $resource->type = $parts[0];
            $resource->subtype = count($parts) > 1 ? $parts[1] : '';
            $resource->content = $result->get('content');

            $courseid = $result->get('courseid');
            if (!isset($coursenames[$courseid])) {
                $course = get_course($courseid);
                $coursenames[$courseid] = $course->fullname;
            }
            $resource->coursename = $coursenames[$courseid];
            $resource->courseurl = (string)\course_get_url($courseid);

            $found[] = $resource;

            if (++$k >= $limit) {
                break;
            }
        }

        return empty($found) ? '' : @json_encode($found);
    }
}
