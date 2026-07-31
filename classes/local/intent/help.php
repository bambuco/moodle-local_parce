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
 * Class help
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class help extends base {
    /**
     * Get the content based on the parameters.
     *
     * @return string The content to be displayed, based on the keywords or topics provided in the parameters.
     */
    public function get_content(): string {
        $help = '';
        switch ($this->context->contextlevel) {
            case CONTEXT_COURSE:
                $help = get_string('intent_help_course', 'local_parce');
                break;
            case CONTEXT_MODULE:
                $help = get_string('intent_help_module', 'local_parce');
                break;
            default:
                $help = get_string('intent_help_default', 'local_parce');
        }

        return $help;
    }
}
