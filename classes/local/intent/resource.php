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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_parce\local\intent;

/**
 * Resolve explicit requests to find or access Moodle resources without a second AI call.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class resource extends content {
    /** Maximum number of activity links returned directly. */
    private const ACTIVITY_LIMIT = 5;

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
        $resourcetypes = $this->params['resourcetype'] ?? null;
        if ($resourcetypes === null) {
            throw new \moodle_exception('intent_resource_notfound', 'local_parce');
        }
        if (!is_array($resourcetypes)) {
            $resourcetypes = [$resourcetypes];
        }
        $resourcetypes = $this->normalise_resource_types($resourcetypes);
        if (empty($resourcetypes)) {
            throw new \moodle_exception('intent_resource_notfound', 'local_parce');
        }

        $keywords = array_values(array_filter(array_map('trim', $keywords), static fn(string $keyword): bool => $keyword !== ''));
        $activitytypes = array_values(array_diff($resourcetypes, ['core_course']));
        if (empty($keywords) && !empty($activitytypes)) {
            $results = $this->get_visible_activities($resourcetypes);
        } else {
            $searchtypes = array_map(
                static fn(string $type): string => $type === 'core_course' ? $type : 'mod_' . $type,
                $resourcetypes
            );
            $results = $this->get_search(implode(' ', $keywords), $keywords, $searchtypes);
        }

        $response = self::format_search_results($results, 'resource_results');
        if ($response === '') {
            throw new \moodle_exception('intent_resource_notfound', 'local_parce');
        }

        return $response;
    }

    /**
     * Return the module types used in the current course and whether each type can provide grades.
     *
     * The catalogue deliberately describes component capabilities rather than inspecting course grades.
     *
     * @param \core\context $context Current question context.
     * @return array<string, bool> Module short names mapped to FEATURE_GRADE_HAS_GRADE support.
     */
    public static function get_module_type_catalogue(\core\context $context): array {
        global $DB;

        $coursecontext = $context->get_course_context(false);
        if (empty($coursecontext) || $coursecontext->instanceid == SITEID) {
            return [];
        }

        $sql = "SELECT DISTINCT m.name
                  FROM {modules} m
                  JOIN {course_modules} cm ON cm.module = m.id
                 WHERE cm.course = :courseid
                       AND cm.deletioninprogress = 0
                       AND m.visible = 1";
        $modulenames = $DB->get_fieldset_sql($sql, ['courseid' => $coursecontext->instanceid]);
        sort($modulenames, SORT_STRING);

        $catalogue = [];
        foreach ($modulenames as $modulename) {
            $catalogue[$modulename] = (bool) plugin_supports(
                'mod',
                $modulename,
                FEATURE_GRADE_HAS_GRADE,
                false
            );
        }

        return $catalogue;
    }

    /**
     * Validate planner resource types and expand the all-modules selector.
     *
     * @param array $resourcetypes Module short names, core_course, or "*"/legacy "mod" for all modules.
     * @return string[] Validated module short names, with core_course preserved.
     */
    private function normalise_resource_types(array $resourcetypes): array {
        $catalogue = self::get_module_type_catalogue($this->context);
        $availabletypes = array_keys($catalogue);
        if (empty($availabletypes)) {
            $availabletypes = array_keys(\core_component::get_plugin_list('mod'));
        }

        $normalised = [];
        foreach ($resourcetypes as $resourcetype) {
            $resourcetype = trim((string) $resourcetype);
            if ($resourcetype === '*' || $resourcetype === 'mod') {
                $normalised = array_merge($normalised, $availabletypes);
                continue;
            }
            if ($resourcetype === 'core_course') {
                $normalised[] = $resourcetype;
                continue;
            }
            if (str_starts_with($resourcetype, 'mod_')) {
                $resourcetype = substr($resourcetype, 4);
            }
            if (in_array($resourcetype, $availabletypes, true)) {
                $normalised[] = $resourcetype;
            }
        }

        return array_values(array_unique($normalised));
    }

    /**
     * List visible activities when the request identifies activity types but has no distinctive search terms.
     *
     * @param array $resourcetypes Validated module short names.
     * @return string JSON encoded activity links, or an empty string for unsupported resource types.
     */
    private function get_visible_activities(array $resourcetypes): string {
        $requestedtypes = array_values(array_diff($resourcetypes, ['core_course']));
        if (empty($requestedtypes)) {
            return '';
        }

        $coursecontext = $this->context->get_course_context(false);
        if (!empty($coursecontext) && $coursecontext->instanceid != SITEID) {
            $courses = [get_course($coursecontext->instanceid)];
        } else {
            $courses = enrol_get_users_courses($this->user->id, true, 'id, fullname');
        }

        $found = [];
        foreach ($courses as $course) {
            $modinfo = get_fast_modinfo($course, $this->user->id);
            foreach ($modinfo->get_cms() as $cm) {
                if (!in_array($cm->modname, $requestedtypes, true) || !$cm->uservisible || empty($cm->url)) {
                    continue;
                }

                $resource = new \stdClass();
                $resource->name = format_string($cm->name, true, ['context' => $cm->context]);
                $resource->url = (string) $cm->url;
                $resource->type = 'mod_' . $cm->modname;
                $resource->coursename = format_string(
                    $course->fullname,
                    true,
                    ['context' => \context_course::instance($course->id)]
                );
                $resource->courseurl = (string) course_get_url($course);
                $resource->content = '';
                $found[] = $resource;

                if (count($found) >= self::ACTIVITY_LIMIT) {
                    break 2;
                }
            }
        }

        return empty($found) ? '' : \local_parce\local\controller::encode_retrieved_items($found);
    }
}
