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
 * Retrieve visible course structure for AI-assisted answers.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course extends base {
    /**
     * Supported structure scopes.
     *
     * @var array<string>
     */
    private const SCOPES = ['overview', 'sections', 'resources'];

    /**
     * Get visible course identity and, for selected scopes, sections or resources.
     *
     * @return string JSON encoded structure records.
     */
    #[\Override]
    public function get_content(): string {
        $scope = $this->normalise_scope($this->params['scope'] ?? 'overview');
        if ($scope === null) {
            throw new \moodle_exception('intent_course_notfound', 'local_parce');
        }

        $keywords = $this->params['content'] ?? [];
        if (!is_array($keywords)) {
            $keywords = [$keywords];
        }
        $keywords = $this->normalise_keywords($keywords);
        $sectionfilter = $this->params['section'] ?? null;
        $resourcetypes = $this->normalise_resource_types($this->params['resourcetype'] ?? ['*']);

        $courses = $this->resolve_courses($keywords, $scope);
        if (empty($courses)) {
            throw new \moodle_exception('intent_course_notfound', 'local_parce');
        }

        $found = [];
        $expand = $scope !== 'overview' && count($courses) === 1;
        foreach ($courses as $course) {
            $coursecontext = \context_course::instance($course->id);
            $modinfo = get_fast_modinfo($course, $this->user->id);
            $found[] = $this->build_course_record($course, $coursecontext);

            if (!$expand) {
                continue;
            }

            $sectionrecords = [];
            $resourcerecords = [];
            foreach ($modinfo->get_section_info_all() as $section) {
                if (empty($section->uservisible) || !$this->matches_section_filter($section, $sectionfilter)) {
                    continue;
                }

                $sectionresources = [];
                foreach ($modinfo->get_cms() as $cm) {
                    if ((int) $cm->sectionnum !== (int) $section->section) {
                        continue;
                    }
                    if (
                        !$cm->uservisible
                        || empty($cm->url)
                        || !in_array($cm->modname, $resourcetypes, true)
                    ) {
                        continue;
                    }
                    if (!$this->matches_keywords(
                        format_string($cm->name, true, ['context' => $cm->context]),
                        $keywords
                    )) {
                        continue;
                    }
                    $sectionresources[] = (object) [
                        'kind' => 'resource',
                        'name' => format_string($cm->name, true, ['context' => $cm->context]),
                        'type' => 'mod_' . $cm->modname,
                        'url' => (string) $cm->url,
                        'section' => (int) $section->section,
                    ];
                }

                if ($scope === 'sections' || $scope === 'resources') {
                    $sectionrecord = (object) [
                        'kind' => 'section',
                        'number' => (int) $section->section,
                        'name' => get_section_name($course, $section),
                        'resourcecount' => count($sectionresources),
                    ];
                    $summary = trim(content_to_text((string) ($section->summary ?? ''), (int) ($section->summaryformat ?? FORMAT_HTML)));
                    if ($summary !== '') {
                        $sectionrecord->content = $summary;
                    }
                    $sectionrecords[] = $sectionrecord;
                }

                if ($scope === 'resources') {
                    $resourcerecords = array_merge($resourcerecords, $sectionresources);
                }
            }

            if ($scope === 'sections') {
                $found = array_merge($found, $sectionrecords);
            } else if ($scope === 'resources') {
                $found = array_merge($found, $sectionrecords, $resourcerecords);
            }
        }

        return $this->encode_with_truncation_flag($found);
    }

    /**
     * Course structure requires an AI call to answer the user's specific question.
     *
     * @return bool
     */
    #[\Override]
    public function require_ia(): bool {
        return true;
    }

    /**
     * Resolve courses for the current context and scope.
     *
     * @param array $keywords Distinctive course terms.
     * @param string $scope Normalised scope.
     * @return \stdClass[]
     */
    private function resolve_courses(array $keywords, string $scope): array {
        $coursecontext = $this->context->get_course_context(false);
        if (!empty($coursecontext) && $coursecontext->instanceid != SITEID) {
            return [get_course($coursecontext->instanceid)];
        }

        $courses = enrol_get_users_courses($this->user->id, true, 'id, fullname, shortname, startdate, enddate');
        if (empty($courses)) {
            return [];
        }

        if ($scope === 'overview') {
            return array_values($courses);
        }

        $matched = [];
        foreach ($courses as $course) {
            $coursename = format_string(
                $course->fullname . ' ' . $course->shortname,
                true,
                ['context' => \context_course::instance($course->id)]
            );
            if ($this->matches_keywords($coursename, $keywords)) {
                $matched[] = $course;
            }
        }

        // Expand sections or resources only when one course is uniquely identified.
        return count($matched) === 1 ? $matched : array_values($courses);
    }

    /**
     * Build the course identity record for the AI payload.
     *
     * @param \stdClass $course Course record.
     * @param \context_course $coursecontext Course context.
     * @return \stdClass
     */
    private function build_course_record(\stdClass $course, \context_course $coursecontext): \stdClass {
        $record = (object) [
            'kind' => 'course',
            'fullname' => format_string($course->fullname, true, ['context' => $coursecontext]),
            'shortname' => format_string($course->shortname, true, ['context' => $coursecontext]),
            'sectioncount' => \local_parce\local\controller::count_visible_sections($course, $this->user->id),
            'startdate' => $this->format_optional_date(
                (int) ($course->startdate ?? 0),
                get_string('nocoursestarttime', 'moodle')
            ),
            'enddate' => $this->format_optional_date(
                (int) ($course->enddate ?? 0),
                get_string('nocourseendtime', 'course')
            ),
        ];

        $enrolment = $this->get_user_enrolment_window((int) $course->id);
        if ($enrolment !== null) {
            $record->enrolmentstart = $enrolment['start'];
            $record->enrolmentend = $enrolment['end'];
        }

        $customfields = $this->get_public_custom_fields((int) $course->id);
        if (!empty($customfields)) {
            $record->customfields = $customfields;
        }

        return $record;
    }

    /**
     * Format a unix timestamp for the AI, or return the fallback when unset.
     *
     * @param int $timestamp Unix timestamp.
     * @param string $fallback Localised text when the date is not set.
     * @return string
     */
    private function format_optional_date(int $timestamp, string $fallback): string {
        return $timestamp > 0 ? userdate($timestamp) : $fallback;
    }

    /**
     * Resolve the user's active enrolment window in a course.
     *
     * When several active enrolments exist, uses the earliest start and the
     * latest end. A zero end on any enrolment means no scheduled end.
     *
     * @param int $courseid Course id.
     * @return array{start: string, end: string}|null
     */
    private function get_user_enrolment_window(int $courseid): ?array {
        $users = enrol_get_course_users($courseid, true, [$this->user->id]);
        if (empty($users)) {
            return null;
        }

        $minstart = null;
        $maxend = null;
        $hasopenend = false;
        foreach ($users as $user) {
            $timestart = (int) ($user->uetimestart ?? 0);
            $timeend = (int) ($user->uetimeend ?? 0);
            if ($minstart === null || $timestart < $minstart) {
                $minstart = $timestart;
            }
            if ($timeend === 0) {
                $hasopenend = true;
            } else if ($maxend === null || $timeend > $maxend) {
                $maxend = $timeend;
            }
        }

        return [
            'start' => $this->format_optional_date(
                (int) $minstart,
                get_string('nocoursestarttime', 'moodle')
            ),
            'end' => $hasopenend
                ? get_string('nocourseendtime', 'course')
                : $this->format_optional_date((int) $maxend, get_string('nocourseendtime', 'course')),
        ];
    }

    /**
     * Public course custom fields visible to everybody (VISIBLETOALL).
     *
     * @param int $courseid Course id.
     * @return array<int, object>
     */
    private function get_public_custom_fields(int $courseid): array {
        $handler = \core_course\customfield\course_handler::create();
        $fieldsdata = $handler->get_instance_data($courseid, true);
        $customfields = [];
        foreach ($fieldsdata as $data) {
            $visibility = (int) ($data->get_field()->get_configdata_property('visibility')
                ?? \core_course\customfield\course_handler::VISIBLETOALL);
            if ($visibility !== \core_course\customfield\course_handler::VISIBLETOALL) {
                continue;
            }
            $value = $data->export_value();
            if ($value === null || $value === '') {
                continue;
            }
            $text = trim(content_to_text((string) $value, FORMAT_HTML));
            if ($text === '') {
                continue;
            }
            $customfields[] = (object) [
                'name' => $data->get_field()->get_formatted_name(),
                'shortname' => $data->get_field()->get('shortname'),
                'value' => $text,
            ];
        }
        return $customfields;
    }

    /**
     * Encode retrieved items and mark the first course record when items were dropped.
     *
     * @param array $items Structure records.
     * @return string
     */
    private function encode_with_truncation_flag(array $items): string {
        $originalcount = count($items);
        $encoded = \local_parce\local\controller::encode_retrieved_items($items);
        if ($encoded === '') {
            throw new \moodle_exception('intent_course_notfound', 'local_parce');
        }
        $decoded = json_decode($encoded, true);
        if (!is_array($decoded) || count($decoded) >= $originalcount || empty($decoded[0])) {
            return $encoded;
        }
        $decoded[0]['truncated'] = true;
        return \local_parce\local\controller::encode_retrieved_items($decoded);
    }

    /**
     * Validate the planner scope.
     *
     * @param mixed $scope Planner scope.
     * @return string|null
     */
    private function normalise_scope(mixed $scope): ?string {
        if (is_array($scope)) {
            $scope = reset($scope);
        }
        if (!is_scalar($scope)) {
            return null;
        }
        $scope = \core_text::strtolower(trim((string) $scope));
        return in_array($scope, self::SCOPES, true) ? $scope : null;
    }

    /**
     * Expand resource type selectors to module short names.
     *
     * @param mixed $resourcetypes Planner resource types.
     * @return string[]
     */
    private function normalise_resource_types(mixed $resourcetypes): array {
        if ($resourcetypes === null || $resourcetypes === '') {
            $resourcetypes = ['*'];
        }
        if (!is_array($resourcetypes)) {
            $resourcetypes = [$resourcetypes];
        }

        $catalogue = resource::get_module_type_catalogue($this->context);
        $available = array_keys($catalogue);
        if (empty($available)) {
            $available = array_keys(\core_component::get_plugin_list('mod'));
        }

        $normalised = [];
        foreach ($resourcetypes as $type) {
            $type = trim((string) $type);
            if ($type === '*' || $type === 'mod') {
                $normalised = array_merge($normalised, $available);
                continue;
            }
            if (str_starts_with($type, 'mod_')) {
                $type = substr($type, 4);
            }
            if (in_array($type, $available, true)) {
                $normalised[] = $type;
            }
        }

        return array_values(array_unique($normalised));
    }

    /**
     * Match an optional section number or name filter.
     *
     * @param \section_info $section Section info.
     * @param mixed $filter Planner section filter.
     * @return bool
     */
    private function matches_section_filter(\section_info $section, mixed $filter): bool {
        if ($filter === null || $filter === '') {
            return true;
        }
        if (is_array($filter)) {
            $filter = reset($filter);
        }
        if (!is_scalar($filter)) {
            return false;
        }
        $filter = trim((string) $filter);
        if ($filter === '') {
            return true;
        }
        if (ctype_digit($filter) || (is_numeric($filter) && (string) (int) $filter === $filter)) {
            return (int) $section->section === (int) $filter;
        }
        $name = \core_text::strtolower(\core_text::specialtoascii(get_section_name($section->course, $section)));
        $needle = \core_text::strtolower(\core_text::specialtoascii($filter));
        return \core_text::strpos($name, $needle) !== false;
    }

    /**
     * Remove generic wording that does not identify a course or activity.
     *
     * @param array $keywords Planner keywords.
     * @return array
     */
    private function normalise_keywords(array $keywords): array {
        $generic = [
            'activity', 'activities', 'course', 'courses', 'module', 'modules', 'my', 'resource', 'resources',
            'section', 'sections', 'actividad', 'actividades', 'curso', 'cursos', 'mi', 'mis', 'modulo',
            'modulos', 'recurso', 'recursos', 'seccion', 'secciones',
        ];
        $terms = [];
        foreach ($keywords as $keyword) {
            $keyword = \core_text::strtolower(\core_text::specialtoascii(trim((string) $keyword)));
            foreach (preg_split('/[^\pL\pN]+/u', $keyword, -1, PREG_SPLIT_NO_EMPTY) as $term) {
                if (!in_array($term, $generic, true)) {
                    $terms[$term] = true;
                }
            }
        }
        return array_keys($terms);
    }

    /**
     * Check whether every distinctive term occurs in the candidate text.
     *
     * @param string $text Candidate text.
     * @param array $keywords Distinctive terms.
     * @return bool
     */
    private function matches_keywords(string $text, array $keywords): bool {
        if (empty($keywords)) {
            return true;
        }
        $text = \core_text::strtolower(\core_text::specialtoascii($text));
        foreach ($keywords as $keyword) {
            if (\core_text::strpos($text, $keyword) === false) {
                return false;
            }
        }
        return true;
    }
}
