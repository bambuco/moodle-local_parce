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

use Behat\Gherkin\Node\TableNode;

/**
 * Behat steps for deterministic Parce widget responses.
 *
 * @package    local_parce
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_parce extends behat_base {
    /**
     * Sets focus on an element without dispatching a click, so keyboard-only
     * interactions (Enter/Space via "I press the ... key") can be tested.
     *
     * @Given /^I focus "(?P<element_string>(?:[^"]|\\")*)" "(?P<selector_string>[^"]*)"$/
     * @param string $element Element we look for
     * @param string $selectortype The type of what we look for
     */
    public function i_focus_on($element, $selectortype): void {
        if (!$this->running_javascript()) {
            throw new \Behat\Mink\Exception\DriverException('Focusing an element requires JavaScript');
        }
        $this->get_selected_node($selectortype, $element)->focus();
    }

    /**
     * Asserts an exact number of visible occurrences of a text on the current page.
     *
     * @Then /^I should see "(?P<text_string>(?:[^"]|\\")*)" exactly "(?P<count_number>\d+)" times$/
     * @param string $text Text to search for
     * @param int $count Expected amount of visible occurrences
     */
    public function i_should_see_exactly_n_times($text, $count): void {
        $count = (int) $count;
        $xpathliteral = behat_context_helper::escape($text);
        $xpath = "//descendant-or-self::*[contains(., {$xpathliteral})]" .
            "[count(descendant::*[contains(., {$xpathliteral})]) = 0]";

        $nodes = $this->getSession()->getPage()->findAll('xpath', $xpath);
        $visiblecount = 0;
        foreach ($nodes as $node) {
            if (!$this->running_javascript() || $node->isVisible()) {
                $visiblecount++;
            }
        }

        if ($visiblecount !== $count) {
            throw new \Behat\Mink\Exception\ExpectationException(
                sprintf('"%s" was found %d time(s) but %d were expected', $text, $visiblecount, $count),
                $this->getSession()
            );
        }
    }

    /**
     * Persist complete turns for portal navigation scenarios.
     *
     * @Given /^the following Parce historical turns exist:$/
     * @param TableNode $table Turns with username, course, key, question and response
     */
    public function historical_turns_exist(TableNode $table): void {
        global $DB;

        foreach ($table->getHash() as $row) {
            $user = $DB->get_record('user', ['username' => $row['username']], '*', MUST_EXIST);
            if ($row['course'] === 'system') {
                $context = \context_system::instance();
            } else {
                $course = $DB->get_record('course', ['shortname' => $row['course']], '*', MUST_EXIST);
                $context = \context_course::instance($course->id);
            }
            $DB->insert_record('local_parce_conversation_entries', (object) [
                'userid' => $user->id,
                'chatid' => $context->id,
                'conversationkey' => hash('sha256', $row['key']),
                'question' => $row['question'],
                'response' => clean_text($row['response'], FORMAT_HTML),
                'timecreated' => time(),
            ]);
        }
    }

    /**
     * Replace the browser request boundary with queued structured results.
     *
     * @Given /^the following Parce answer attempts are queued:$/
     * @param TableNode $table Result rows
     */
    public function queue_answer_attempts(TableNode $table): void {
        $responses = [];
        foreach ($table->getHash() as $row) {
            $responses[] = [
                'status' => $row['status'],
                'successful' => $row['successful'] === '1',
                'retryable' => $row['retryable'] === '1',
                'answer' => $row['answer'],
                'newconversation' => $row['newconversation'] === '1',
                'usagepercentage' => (int) $row['usagepercentage'],
            ];
        }

        $json = json_encode($responses, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $this->execute_script(<<<JS
            (function() {
                var handler = require('local_parce/chat-handler');
                var responses = {$json};
                handler.submitQuestion = function() {
                    return Promise.resolve(responses.shift());
                };
            }());
            JS);
    }

    /**
     * Seed the browser renderer as though the complete active history had loaded.
     *
     * @Given /^the Parce widget has this active conversation:$/
     * @param TableNode $table Conversation entries
     */
    public function seed_active_conversation(TableNode $table): void {
        $entries = [];
        foreach ($table->getHash() as $row) {
            $entries[] = [
                'role' => $row['role'],
                'content' => $row['content'],
                'timestamp_formatted' => '10:00',
            ];
        }

        $json = json_encode($entries, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $this->execute_script(<<<JS
            (function() {
                var ui = require('local_parce/chat-ui');
                ui.renderHistoryEntries({$json});
                ui.hasLoadedHistory = true;
                ui.historyPromise = null;
            }());
            JS);
    }

    /**
     * Delay one complete history load to exercise open/load/send ordering.
     *
     * @Given /^loading this Parce active conversation is delayed:$/
     * @param TableNode $table Conversation entries
     */
    public function delay_active_conversation(TableNode $table): void {
        $entries = [];
        foreach ($table->getHash() as $row) {
            $entries[] = [
                'role' => $row['role'],
                'content' => $row['content'],
                'timestamp_formatted' => '10:00',
            ];
        }

        $json = json_encode($entries, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $this->execute_script(<<<JS
            (function() {
                var ui = require('local_parce/chat-ui');
                var entries = {$json};
                ui.hasLoadedHistory = false;
                ui.historyPromise = null;
                ui.ensureHistoryLoaded = function() {
                    if (this.hasLoadedHistory) {
                        return Promise.resolve();
                    }
                    if (!this.historyPromise) {
                        this.showLoading();
                        this.historyPromise = new Promise((resolve) => {
                            window.setTimeout(() => {
                                this.renderHistoryEntries(entries);
                                this.hasLoadedHistory = true;
                                this.historyPromise = null;
                                this.hideLoading();
                                resolve();
                            }, 1000);
                        });
                    }
                    return this.historyPromise;
                };
            }());
            JS);
    }
}
