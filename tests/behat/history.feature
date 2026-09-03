@local @local_parce @javascript
Feature: Browse persistent Parce conversation history
  In order to review completed conversations without changing the active chat
  Users and authorised administrators can navigate the historical portal

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | student1 | Student   | One      |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following Parce historical turns exist:
      | username | course | key    | question       | response          |
      | student1 | C1     | first  | First question | <p>First answer</p> |

  Scenario: An owner opens a conversation without leaving the history page
    Given I log in as "student1"
    When I visit "/local/parce/history.php"
    Then I should see "Course 1"
    When I click on "Course 1" "button"
    Then I should see "Turns: 1"
    When I click on "Turns: 1" "button"
    Then I should see "First question"
    And I should see "First answer"

  Scenario: Search returns grouped minimal results and highlights the selected conversation
    Given I log in as "student1"
    When I visit "/local/parce/history.php"
    And I set the field "Search conversations" to "First question"
    And I press "Search"
    Then I should see "Course 1"
    And I should see "Turns: 1"
    When I click on "Turns: 1" "button"
    Then I should see "First question" in the "mark" element

  Scenario: An administrator enters history from a course context
    Given I log in as "admin"
    When I am on "Course 1" course homepage
    And I click on "Parce conversation history" "link"
    Then I should see "Student One"
    And I should not see "First question"
