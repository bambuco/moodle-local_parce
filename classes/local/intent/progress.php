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
 * Retrieve the current user's visible course and activity completion progress.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class progress extends base {
    /** Maximum number of progress records sent to the answer provider. */
    private const RESULT_LIMIT = 50;

    /**
     * Get visible completion progress in the current course or the user's enrolled courses.
     *
     * @return string JSON encoded course summaries and activity completion records.
     */
    #[\Override]
    public function get_content(): string {
        global $CFG;

        require_once($CFG->libdir . '/completionlib.php');

        $keywords = $this->params['progress'] ?? $this->params['content'] ?? [];
        if (!is_array($keywords)) {
            $keywords = [$keywords];
        }
        $keywords = $this->normalise_keywords($keywords);
        $statusfilter = $this->normalise_status_filter($this->params['status'] ?? null);
        if ($statusfilter === null) {
            throw new \moodle_exception('intent_progress_notfound', 'local_parce');
        }

        $coursecontext = $this->context->get_course_context(false);
        if (!empty($coursecontext) && $coursecontext->instanceid != SITEID) {
            $courses = [get_course($coursecontext->instanceid)];
        } else {
            $courses = enrol_get_users_courses($this->user->id, true, 'id, fullname, shortname, enablecompletion');
        }

        $found = [];
        foreach ($courses as $course) {
            $completion = new \completion_info($course);
            if (
                !$completion->is_enabled()
                || !$completion->is_tracked_user($this->user->id)
                || !completion_can_view_data($this->user->id, $course)
            ) {
                continue;
            }

            $coursecontext = \context_course::instance($course->id);
            $coursename = format_string($course->fullname, true, ['context' => $coursecontext]);
            $coursematches = $this->matches_keywords($coursename, $keywords);
            $activities = [];
            $completedcount = 0;
            $trackedactivities = $completion->get_user_activities_with_completion($this->user->id);
            $modinfo = get_fast_modinfo($course, $this->user->id);

            foreach (array_keys($trackedactivities) as $cmid) {
                $cm = $modinfo->get_cm($cmid);
                $data = $completion->get_data($cm, false, $this->user->id);
                $status = $this->get_status((int) $data->completionstate);
                if ($status === 'complete' || $status === 'passed') {
                    $completedcount++;
                }
                if (!$coursematches && !$this->matches_keywords($cm->name, $keywords)) {
                    continue;
                }
                if (!$this->matches_status_filter($status, $statusfilter)) {
                    continue;
                }

                $activity = new \stdClass();
                $activity->course = $coursename;
                $activity->activity = format_string($cm->name, true, ['context' => $cm->context]);
                $activity->type = $cm->modname;
                $activity->status = $status;
                if ($status !== 'incomplete' && !empty($data->timemodified)) {
                    $activity->completedat = userdate((int) $data->timemodified);
                }
                $activities[] = $activity;
            }

            $percentage = \core_completion\progress::get_course_progress_percentage($course, $this->user->id);
            $coursecomplete = $completion->is_course_complete($this->user->id);
            if ($statusfilter === '' && $coursematches && ($percentage !== null || $coursecomplete)) {
                $summary = new \stdClass();
                $summary->course = $coursename;
                $summary->type = 'course_summary';
                $summary->coursecomplete = $coursecomplete;
                if ($percentage !== null) {
                    $summary->coursecompletionpercentage = round($percentage, 2);
                }
                $summary->completedvisibleactivities = $completedcount;
                $summary->totalvisibletrackedactivities = count($trackedactivities);
                if (!empty($trackedactivities)) {
                    $summary->activitycompletionpercentage = round(
                        ($completedcount / count($trackedactivities)) * 100,
                        2
                    );
                }
                $found[] = $summary;
                if (count($found) >= self::RESULT_LIMIT) {
                    break;
                }
            }

            foreach ($activities as $activity) {
                $found[] = $activity;
                if (count($found) >= self::RESULT_LIMIT) {
                    break 2;
                }
            }
        }

        if (empty($found)) {
            throw new \moodle_exception('intent_progress_notfound', 'local_parce');
        }

        return \local_parce\local\controller::encode_retrieved_items($found);
    }

    /**
     * Progress data requires an AI call to answer the user's specific question.
     *
     * @return bool
     */
    #[\Override]
    public function require_ia(): bool {
        return true;
    }

    /**
     * Remove generic question words which do not identify a course or activity.
     *
     * @param array $keywords Planner-supplied keywords.
     * @return array
     */
    private function normalise_keywords(array $keywords): array {
        $generic = [
            'activity', 'activities', 'complete', 'completed', 'completion', 'course', 'incomplete', 'my',
            'failed', 'passed', 'pending', 'progress', 'actividad', 'actividades', 'aprobada', 'aprobadas',
            'completa', 'completada', 'completadas', 'completar', 'curso', 'mi', 'mis', 'pendiente',
            'pendientes', 'progreso', 'reprobada', 'reprobadas',
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
     * Convert a planner status to a supported completion state.
     *
     * @param mixed $status Planner-supplied status.
     * @return string|null Empty or a supported state, null when a supplied value is invalid.
     */
    private function normalise_status_filter(mixed $status): ?string {
        if ($status === null || $status === '') {
            return '';
        }
        if (is_array($status)) {
            $status = reset($status);
        }
        if (!is_scalar($status)) {
            return null;
        }
        $status = \core_text::strtolower(trim((string) $status));
        return match ($status) {
            'complete', 'completed', 'completa', 'completada', 'completado' => 'completed',
            'passed', 'pass', 'aprobada', 'aprobado' => 'passed',
            'failed', 'fail', 'reprobada', 'reprobado' => 'failed',
            'incomplete', 'pending', 'incompleta', 'pendiente' => 'incomplete',
            default => null,
        };
    }

    /**
     * Check an activity's state against an optional planner filter.
     *
     * @param string $status Activity completion state.
     * @param string $filter Normalised planner filter.
     * @return bool
     */
    private function matches_status_filter(string $status, string $filter): bool {
        if ($filter === '') {
            return true;
        }
        if ($filter === 'completed') {
            return $status !== 'incomplete';
        }
        return $status === $filter;
    }

    /**
     * Convert a Moodle completion constant to a stable provider value.
     *
     * @param int $state Moodle completion state.
     * @return string
     */
    private function get_status(int $state): string {
        return match ($state) {
            COMPLETION_COMPLETE_PASS => 'passed',
            COMPLETION_COMPLETE_FAIL => 'failed',
            COMPLETION_COMPLETE => 'complete',
            default => 'incomplete',
        };
    }

    /**
     * Check whether every distinctive term occurs in a course or activity name.
     *
     * @param string $text Course or activity name.
     * @param array $keywords Distinctive search terms.
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
