@mod @mod_kahoodle @javascript
Feature: Results and reports without plus
  As a teacher
  I can view results and manage questions from completed rounds

  Background:
    Given the following config values are set as admin:
      | disablekahoodleplus | 1 |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Terry     | Teacher  | teacher1@example.com |
      | student1 | Sam       | Student  | student1@example.com |
      | student2 | Alex      | Adams    | student2@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    And the following "activities" exist:
      | activity | name          | course | idnumber  |
      | kahoodle | Test Kahoodle | C1     | kahoodle1 |
    And the following "mod_kahoodle > questions" exist:
      | kahoodle      | questiontext | questionconfig      |
      | Test Kahoodle | Question 1   | Option A\n*Option B |
      | Test Kahoodle | Question 2   | *Yes\nNo            |
    And the kahoodle "Test Kahoodle" round stage is "lobby"
    And the following "mod_kahoodle > participants" exist:
      | kahoodle      | user     | displayname | totalscore |
      | Test Kahoodle | student1 | Sam         | 1700       |
      | Test Kahoodle | student2 | Alex        | 600        |
    And the following "mod_kahoodle > responses" exist:
      | kahoodle      | user     | question | response | iscorrect | points |
      | Test Kahoodle | student1 | 1        | 2        | 1         | 900    |
      | Test Kahoodle | student1 | 2        | 1        | 1         | 800    |
      | Test Kahoodle | student2 | 1        | 1        | 0         | 0      |
      | Test Kahoodle | student2 | 2        | 1        | 1         | 600    |
    And the kahoodle "Test Kahoodle" round stage is "archived"

  Scenario: Teacher views rounds list on results page without plus
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I follow "View results"
    # Round card shows status and stats for the completed round.
    And "//dd[@data-field='status'][contains(.,'Completed')]" "xpath_element" should exist in the "Round 1" "mod_kahoodle > round result"
    And "//dd[@data-field='participants'][contains(.,'2')]" "xpath_element" should exist in the "Round 1" "mod_kahoodle > round result"
    # Without plus, per-round report links are disabled buttons.
    And "View participants" "button" should exist in the "Round 1" "mod_kahoodle > round result"
    And "View participants" "link" should not exist in the "Round 1" "mod_kahoodle > round result"
    And "View statistics" "button" should exist in the "Round 1" "mod_kahoodle > round result"
    And "View statistics" "link" should not exist in the "Round 1" "mod_kahoodle > round result"
    And "Manage questions" "link" should exist in the "Round 1" "mod_kahoodle > round result"
    # Without plus, all-rounds participants link is shown even with 1 completed round.
    And "All rounds: Participants" "link" should exist
    # Without plus, all-rounds statistics is a disabled button.
    And "All rounds: Statistics" "button" should exist
    And "All rounds: Statistics" "link" should not exist

  Scenario: Teacher views questions management page from results
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I follow "View results"
    And I click on "Manage questions" "link" in the "Round 1" "mod_kahoodle > round result"
    # Simple test for contents, since questions management page is covered by other tests.
    And I should see "Question 1"
    And I should see "Question 2"
    When I click on "Filters" "button"
    And I set the following fields in the "Question text" "core_reportbuilder > Filter" to these values:
      | Question text operator | Contains   |
      | Question text value    | Question 1 |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    When I click on "Filters" "button"
    And I should see "Question 1"
    And I should not see "Question 2"
