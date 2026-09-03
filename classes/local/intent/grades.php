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
 * Retrieve the current user's visible grades for AI-assisted answers.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grades extends base {
    /** Maximum number of grade items sent to the answer provider. */
    private const RESULT_LIMIT = 50;

    /**
     * Get visible grade items in the current course or the user's enrolled courses.
     *
     * @return string JSON encoded visible grade items.
     */
    #[\Override]
    public function get_content(): string {
        $keywords = $this->params['grades'] ?? $this->params['content'] ?? $this->params;
        if (!is_array($keywords)) {
            $keywords = [$keywords];
        }
        $keywords = $this->normalise_keywords($keywords);

        $coursecontext = $this->context->get_course_context(false);
        if (!empty($coursecontext) && $coursecontext->instanceid != SITEID) {
            $courses = [get_course($coursecontext->instanceid)];
        } else {
            $courses = enrol_get_users_courses($this->user->id, true, 'id, fullname, shortname, showgrades');
        }

        $found = [];
        foreach ($courses as $course) {
            if (empty($course->showgrades)) {
                continue;
            }

            try {
                $report = \gradereport_user\external\user::get_grade_items($course->id, $this->user->id);
            } catch (\moodle_exception $e) {
                continue;
            }

            foreach ($report['usergrades'][0]['gradeitems'] ?? [] as $item) {
                $itemname = trim((string) ($item['itemname'] ?? ''));
                $coursename = format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]);
                if (!$this->matches_keywords($coursename . ' ' . $itemname, $keywords)) {
                    continue;
                }

                $grade = new \stdClass();
                $grade->course = $coursename;
                $grade->item = $itemname !== '' ? $itemname : get_string('coursetotal', 'grades');
                $grade->type = $item['itemtype'] ?? '';
                $grade->grade = content_to_text((string) ($item['gradeformatted'] ?? '-'), FORMAT_HTML);
                if (!empty($item['rangeformatted'])) {
                    $grade->range = content_to_text((string) $item['rangeformatted'], FORMAT_HTML);
                }
                if (!empty($item['percentageformatted'])) {
                    $grade->percentage = content_to_text((string) $item['percentageformatted'], FORMAT_HTML);
                }
                if (!empty($item['feedback'])) {
                    $grade->feedback = content_to_text(
                        (string) $item['feedback'],
                        (int) ($item['feedbackformat'] ?? FORMAT_MOODLE)
                    );
                }
                if (!empty($item['gradedategraded'])) {
                    $grade->gradedat = userdate((int) $item['gradedategraded']);
                }
                $found[] = $grade;

                if (count($found) >= self::RESULT_LIMIT) {
                    break 2;
                }
            }
        }

        if (empty($found)) {
            throw new \moodle_exception('intent_grades_notfound', 'local_parce');
        }

        return \local_parce\local\controller::encode_retrieved_items($found);
    }

    /**
     * Grade data requires an AI call to answer the user's specific question.
     *
     * @return bool
     */
    #[\Override]
    public function require_ia(): bool {
        return true;
    }

    /**
     * Remove generic question words which do not identify a course or grade item.
     *
     * @param array $keywords Planner-supplied keywords.
     * @return array
     */
    private function normalise_keywords(array $keywords): array {
        $generic = [
            'grade', 'grades', 'score', 'scores', 'mark', 'marks', 'course', 'my',
            'calificacion', 'calificaciones', 'nota', 'notas', 'puntaje', 'puntajes', 'curso', 'mis', 'mi',
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
     * Check whether every distinctive term occurs in the course or item name.
     *
     * @param string $text Course and grade item names.
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
