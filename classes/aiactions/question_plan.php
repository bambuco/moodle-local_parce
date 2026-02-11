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
 * Question plan AI action.
 *
 * This action is responsible for creating a structured plan or outline
 * based on a user's question or topic.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_parce\aiactions;

/**
 * Question plan action class.
 *
 * Extends the core generate_text action with a custom system instruction
 * configured via local_parce settings. Supports context data for structured processing.
 */
class question_plan extends \core_ai\aiactions\generate_text {
    /**
     * Context data to include in the question plan request.
     *
     * @var array Context information (courses, topics, dates, etc.)
     */
    protected array $context = [];

    /**
     * Set the context for this question plan action.
     *
     * The context is passed to the AI provider as structured data
     * to help generate more relevant and targeted plans.
     *
     * @param array $context The context data.
     * @return void
     */
    public function set_context(array $context): void {
        $this->context = $context;
    }

    /**
     * Get a configuration value.
     *
     * Overrides parent to support 'context' parameter.
     *
     * @param string $name The configuration option name.
     * @return mixed The value of the configuration option.
     */
    #[\Override]
    public function get_configuration(string $name): mixed {
        if ($name === 'context') {
            return $this->context;
        }
        return parent::get_configuration($name);
    }

    /**
     * Get a new text action to simulate a original core action with the same parameters but with a custom system instruction.
     *
     * @return \core_ai\aiactions\generate_text The new text action with the custom system instruction.
     */
    public function get_generate_text_action(): \core_ai\aiactions\generate_text {
        $action = new \core_ai\aiactions\generate_text(
            contextid: $this->contextid,
            userid: $this->userid,
            prompttext: $this->prompttext,
        );

        return $action;
    }

    /**
     * Get the class name of the response object.
     *
     * @return string The class name of the response object.
     */
    public static function get_response_classname(): string {
        return '\local_parce\aiactions\responses\response_question_plan';
    }
}
