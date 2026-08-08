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

/**
 * Simulated provider fixture for question handler tests.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_parce\tests\local;

/**
 * Minimal provider used by the simulated gateway.
 */
class test_provider extends \core_ai\provider {
    /**
     * Return the supported action list.
     *
     * @return string[]
     */
    public static function get_action_list(): array {
        return [\core_ai\aiactions\generate_text::class];
    }

    /**
     * Report that the fixture is configured.
     *
     * @return bool
     */
    public function is_provider_configured(): bool {
        return true;
    }
}
