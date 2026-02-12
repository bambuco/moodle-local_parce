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
 * Class base
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class base {
    /**
     * Class constructor.
     *
     * @param \core\context $context Intention context.
     * @param ?object $user The user for whom the intent is being processed.
     * @param array $params Can be a string with keywords or an array with more specific parameters.
     */
    public function __construct(
        /** @var \core\context Intention context. */
        protected \core\context $context,
        /** @var ?object User. */
        protected ?object $user,
        /** @var array Intent params. */
        protected array $params = []
    ) {
        global $USER;

        if (empty($this->user) || !is_object($this->user)) {
            $this->user = $USER;
        }
    }

    /**
     * Get the content based on the parameters.
     *
     * @return string The content to be displayed, based on the keywords or topics provided in the parameters.
     */
    public function get_content(): string {
        return get_string('default_intent_response', 'local_parce');
    }

    /**
     * Indicates that this intent requires IA processing to retrieve the content,
     * as it may involve complex logic or external API calls to gather the relevant
     * information based on the provided keywords or topics.
     *
     * @return bool True, indicating that IA processing is required for this intent.
     */
    public function require_ia(): bool {
        return false;
    }
}
