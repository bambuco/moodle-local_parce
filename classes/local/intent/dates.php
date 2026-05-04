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

use core_calendar\local\api as calendar_api;
use core_calendar\local\event\container as calendar_container;

/**
 * Class dates
 * Dates intent retrieves upcoming events and deadlines based on date-related queries.
 *
 * Uses the core calendar local API which routes through the event_vault and
 * event_factory, applying per-module visibility callbacks so that
 * restricted/hidden events are excluded.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dates extends content {

    /**
     * Get the content based on the date parameters.
     *
     * Searches for events and activities using the date keywords provided by the question_plan.
     *
     * @return string The content to be displayed, based on the date keywords provided in the parameters.
     */
    #[\Override]
    public function get_content(): string {
        if (empty($this->params)) {
            return get_string('intent_dates_default', 'local_parce');
        }

        // The params may come as an associative array like ["dates" => "próximamente"].
        $keywords = $this->params['dates'] ?? $this->params;
        if (!is_array($keywords)) {
            $keywords = [$keywords];
        }
        $q = implode(' ', $keywords);
        $content = $this->get_events($q);

        if (empty($content)) {
            $allowopenanswer = get_config('local_parce', 'allowopenanswer');
            if (!$allowopenanswer) {
                throw new \moodle_exception('intent_dates_notfound', 'local_parce');
            }
            return '';
        }

        return $content;
    }

    /**
     * Get upcoming calendar events formatted as JSON for the AI to process.
     *
     * Uses core_calendar\local\api::get_events() which routes through the event_vault
     * and event_factory, applying component visibility callbacks (core_calendar_is_event_visible)
     * so that restricted/hidden module events are properly excluded.
     *
     * @param string $keywords Search keywords to filter events.
     * @return string JSON encoded string of found events or empty string.
     */
    private function get_events(string $keywords): string {
        global $CFG;
        require_once($CFG->dirroot . '/calendar/lib.php');

        $coursecontext = $this->context->get_course_context(false);

        $now = time();
        $lookahead = 90 * DAYSECS;

        calendar_container::set_requesting_user($this->user->id);

        $usersfilter = [$this->user->id];
        $groupsfilter = null;
        $coursesfilter = null;

        if (!empty($coursecontext) && $coursecontext->instanceid != SITEID) {
            $coursesfilter = [$coursecontext->instanceid];
            $coursegroups = groups_get_user_groups($coursecontext->instanceid, $this->user->id);
            if (!empty($coursegroups[0])) {
                $groupsfilter = $coursegroups[0];
            }
        } else {
            $courses = enrol_get_my_courses('id', 'id ASC');
            $courseids = array_keys($courses);
            $courseids[] = SITEID;
            $coursesfilter = $courseids;

            $groupsfilter = [];
            foreach ($courseids as $courseid) {
                $coursegroups = groups_get_user_groups($courseid, $this->user->id);
                if (!empty($coursegroups[0])) {
                    $groupsfilter = array_merge($groupsfilter, $coursegroups[0]);
                }
            }
            if (empty($groupsfilter)) {
                $groupsfilter = null;
            }
        }

        $events = calendar_api::get_events(
            $now,
            $now + $lookahead,
            null,
            null,
            null,
            null,
            40,
            null,
            $usersfilter,
            $groupsfilter,
            $coursesfilter,
            null,
            true,
            true
        );

        if (empty($events)) {
            return '';
        }

        $found = [];
        $resultlimit = 10;
        $keywordslower = strtolower($keywords);
        $coursenames = [];

        foreach ($events as $event) {
            $name = $event->get_name();
            $description = $event->get_description() ? $event->get_description()->get_value() : '';

            // Filter by keywords if provided.
            if (!empty($keywordslower)) {
                $nametext = strtolower($name . ' ' . $description);
                $words = explode(' ', $keywordslower);
                $match = false;
                foreach ($words as $word) {
                    if (!empty($word) && strpos($nametext, $word) !== false) {
                        $match = true;
                        break;
                    }
                }
                if (!$match) {
                    continue;
                }
            }

            $resource = $this->format_event($event, $name, $description, $coursenames);
            $found[] = $resource;

            if (count($found) >= $resultlimit) {
                break;
            }
        }

        // If keyword filtering returned no results, return all events (up to limit).
        if (empty($found) && !empty($keywordslower)) {
            foreach ($events as $event) {
                $name = $event->get_name();
                $description = $event->get_description() ? $event->get_description()->get_value() : '';

                $resource = $this->format_event($event, $name, $description, $coursenames);
                $found[] = $resource;

                if (count($found) >= $resultlimit) {
                    break;
                }
            }
        }

        return empty($found) ? '' : @json_encode($found);
    }

    /**
     * Format a single calendar event into a standard resource object.
     *
     * @param object $event The calendar event object from the calendar API.
     * @param string $name The event name.
     * @param string $description The event description.
     * @param array $coursenames Cache of course ID => fullname mappings (passed by reference).
     * @return \stdClass The formatted resource.
     */
    private function format_event(object $event, string $name, string $description, array &$coursenames): \stdClass {
        $resource = new \stdClass();
        $resource->name = $name;
        $resource->type = 'event';
        $resource->content = $description;

        $times = $event->get_times();
        $resource->timestart = userdate($times->get_start_time()->getTimestamp());

        $start = $times->get_start_time()->getTimestamp();
        $end = $times->get_end_time()->getTimestamp();
        if ($end > $start) {
            $resource->timeend = userdate($end);
        }

        $course = $event->get_course();
        if ($course) {
            $courseid = $course->get('id');
            if (!isset($coursenames[$courseid])) {
                $courseobj = get_course($courseid);
                $coursenames[$courseid] = $courseobj->fullname;
            }
            $resource->coursename = $coursenames[$courseid];
            $resource->courseurl = (string)\course_get_url($courseid);
        }

        return $resource;
    }
}
