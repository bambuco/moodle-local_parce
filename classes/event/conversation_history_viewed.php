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

namespace local_parce\event;

/**
 * A privileged user viewed another user's conversation history.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class conversation_history_viewed extends \core\event\base {
    /**
     * Initialise event data.
     */
    protected function init(): void {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Return the event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventconversationhistoryviewed', 'local_parce');
    }

    /**
     * Return the event description.
     *
     * @return string
     */
    public function get_description(): string {
        $keys = $this->other['conversationkeys'] ?? '';
        $keydescription = $keys === '' ? '' : " Conversation keys: '{$keys}'.";
        return "The user with id '$this->userid' viewed the Parce conversation history of the user with id " .
            "'$this->relateduserid' in the context with id '$this->contextid'.{$keydescription}";
    }

    /**
     * Validate required event data.
     */
    protected function validate_data(): void {
        parent::validate_data();
        if (empty($this->relateduserid)) {
            throw new \coding_exception('The relateduserid value must be set.');
        }
    }
}
