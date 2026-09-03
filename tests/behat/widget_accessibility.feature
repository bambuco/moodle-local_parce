# This file is part of Moodle - http://moodle.org/
#
# Moodle is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# Moodle is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

@local @local_parce @javascript
Feature: Accessible operation of the Parce chat widget
  In order to use the active chat without a pointing device
  As a user
  I can open, close and navigate the non-modal dialog with coherent state

  Background:
    Given the following config values are set as admin:
      | config  | value | plugin      |
      | enabled | 1     | local_parce |
    And the following "courses" exist:
      | fullname    | shortname |
      | Test course | C1        |
    And I log in as "admin"

  Scenario: Enter and Space open the widget and Escape restores focus
    Given I am on site homepage
    And I focus "Open chat" "button"
    When I press the enter key
    Then the "aria-expanded" attribute of "Open chat" "button" should contain "true"
    And the "aria-hidden" attribute of "#local_parce-chat-window-container" "css_element" should contain "false"
    And the focused element is "Message input" "field"
    When I press the escape key
    Then the "aria-expanded" attribute of "Open chat" "button" should contain "false"
    And the "aria-hidden" attribute of "#local_parce-chat-window-container" "css_element" should contain "true"
    And the focused element is "Open chat" "button"
    When I press the space key
    Then the "aria-expanded" attribute of "Open chat" "button" should contain "true"
    And the focused element is "Message input" "field"

  Scenario: Open state persists across page navigation in the same tab
    Given I am on site homepage
    And I click on "Open chat" "button"
    When I am on "Test course" course homepage
    Then the "aria-expanded" attribute of "Open chat" "button" should contain "true"

  Scenario: Dialog remains operable in a mobile viewport
    Given I change viewport size to "mobile"
    And I am on site homepage
    When I click on "Open chat" "button"
    Then "#local_parce-chat-window-container" "css_element" should be visible
    And the focused element is "Message input" "field"
    When I click on "Close chat" "button"
    Then the focused element is "Open chat" "button"

  Scenario: Retrying a failed send does not duplicate the question
    Given I am on site homepage
    And the Parce widget has this active conversation:
      | role   | content |
      | system | Welcome |
    And the following Parce answer attempts are queued:
      | status  | successful | retryable | answer          | newconversation | usagepercentage |
      | error   | 0          | 1         | Temporary error | 0               | 10              |
      | success | 1          | 0         | Final answer    | 0               | 20              |
    And I click on "Open chat" "button"
    When I set the field "Message input" to "Only once"
    And I click on "Send" "button"
    Then I should see "Temporary error" in the "#local_parce-messages-container" "css_element"
    When I click on "Try again" "button"
    Then I should see "Final answer" in the "#local_parce-messages-container" "css_element"
    And I should see "Only once" exactly "1" times

  Scenario: Rollover replaces old active messages without duplicating the pending turn
    Given I am on site homepage
    And the Parce widget has this active conversation:
      | role   | content    |
      | user   | Old turn   |
      | system | Old answer |
    And the following Parce answer attempts are queued:
      | status  | successful | retryable | answer     | newconversation | usagepercentage |
      | success | 1          | 0         | New answer | 1               | 5               |
    And I click on "Open chat" "button"
    When I set the field "Message input" to "New turn"
    And I click on "Send" "button"
    Then I should not see "Old turn" in the "#local_parce-messages-container" "css_element"
    And I should see "New turn" exactly "1" times
    And I should see "New answer" exactly "1" times

  Scenario: Sending while the active conversation loads preserves every turn once
    Given I am on site homepage
    And loading this Parce active conversation is delayed:
      | role   | content        |
      | system | Earlier answer |
    And the following Parce answer attempts are queued:
      | status  | successful | retryable | answer        | newconversation | usagepercentage |
      | success | 1          | 0         | Current answer | 0               | 30              |
    And I click on "Open chat" "button"
    When I set the field "Message input" to "Current question"
    And I click on "Send" "button"
    Then I should see "Earlier answer" in the "#local_parce-messages-container" "css_element"
    And I should see "Current question" exactly "1" times
    And I should see "Current answer" exactly "1" times

  Scenario: Rate limit is exposed as a distinct visible state
    Given I am on site homepage
    And the Parce widget has this active conversation:
      | role   | content |
      | system | Welcome |
    And the following Parce answer attempts are queued:
      | status       | successful | retryable | answer          | newconversation | usagepercentage |
      | rate_limited | 0          | 1         | Try again later | 0               | 0               |
    And I click on "Open chat" "button"
    When I set the field "Message input" to "Question"
    And I click on "Send" "button"
    Then ".local_parce-message-rate_limited" "css_element" should be visible
    And I should see "Try again later" in the ".local_parce-message-rate_limited" "css_element"
