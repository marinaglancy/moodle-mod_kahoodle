@mod @mod_kahoodle
Feature: Managing questions in Kahoodle
  In order to create engaging quizzes
  As a teacher
  I need to be able to add and edit questions in Kahoodle

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Terry     | Teacher  | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity | name          | course | idnumber  |
      | kahoodle | Test Kahoodle | C1     | kahoodle1 |

  @javascript
  Scenario: Adding a question manually
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I navigate to "Questions" in current page administration
    And I click on "Add question" "button"
    And I click on "Multiple choice" "link"
    And I set the field "Question text" in the "Add question: Multiple choice" "dialogue" to "What is the capital of France?"
    And I set the field "Answer options" to multiline:
      """
      Paris
      *London
      Berlin
      Madrid
      """
    And I click on "Save changes" "button" in the "Add question: Multiple choice" "dialogue"
    Then I should see "What is the capital of France?"

  @javascript
  Scenario: Editing a question
    Given the following "mod_kahoodle > questions" exist:
      | kahoodle  | questiontext   | questionconfig |
      | kahoodle1 | What is 2 + 2? | 3\n*4\n5\n6    |
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I navigate to "Questions" in current page administration
    Then I should see "What is 2 + 2?"
    When I open the action menu in "What is 2 + 2?" "table_row"
    And I choose "Edit question" in the open action menu
    And I set the field "Question text" in the "Edit question" "dialogue" to "What is 2 + 3?"
    And I set the field "Answer options" to multiline:
      """
      4
      *5
      6
      7
      """
    And I click on "Save changes" "button" in the "Edit question" "dialogue"
    Then I should see "What is 2 + 3?"
    And I should not see "What is 2 + 2?"
