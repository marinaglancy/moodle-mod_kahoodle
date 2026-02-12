@mod @mod_kahoodle @javascript
Feature: Results and reports
  As a teacher
  I can view results, participant lists, and statistics for completed rounds

  Background:
    Given the following "users" exist:
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

  Scenario: Teacher views rounds list on results page
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I follow "View results"
    # Round card shows status and stats for the completed round.
    And "//dd[@data-field='status'][contains(.,'Completed')]" "xpath_element" should exist in the "Round 1" "mod_kahoodle > round result"
    And "//dd[@data-field='participants'][contains(.,'2')]" "xpath_element" should exist in the "Round 1" "mod_kahoodle > round result"
    And "View participants" "link" should exist in the "Round 1" "mod_kahoodle > round result"
    And "View statistics" "link" should exist in the "Round 1" "mod_kahoodle > round result"
    And "Manage questions" "link" should exist in the "Round 1" "mod_kahoodle > round result"
    # Only one round, so all-rounds buttons should not exist.
    And I should not see "All rounds: Participants"
    And I should not see "All rounds: Statistics"

  Scenario: Teacher views participants report
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I follow "View results"
    And I click on "View participants" "link" in the "Round 1" "mod_kahoodle > round result"
    Then the following should exist in the "Participants" table:
      | Participant | First name  | Rank | Score | Correct answers | Questions answered |
      | Sam         | Sam Student | 1    | 1,700 | 2               | 2                  |
      | Alex        | Alex Adams  | 2    | 600   | 1               | 2                  |
    When I click on "Filters" "button"
    And I set the following fields in the "Display name" "core_reportbuilder > Filter" to these values:
      | Display name operator | Contains |
      | Display name value    | Sam      |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    Then the following should exist in the "Participants" table:
      | Participant | First name  | Rank | Score | Correct answers | Questions answered |
      | Sam         | Sam Student | 1    | 1,700 | 2               | 2                  |
    And I should not see "Alex"

  Scenario: Teacher views statistics report
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I follow "View results"
    And I click on "View statistics" "link" in the "Round 1" "mod_kahoodle > round result"
    Then I should see "Total participants"
    And the following should exist in the "Statistics" table:
      | Order | Question type   | Question text | Total responses | Correct responses | Average score |
      | 1     | Multiple choice | Question 1    | 2               | 1                 | 450.0         |
      | 2     | Multiple choice | Question 2    | 2               | 2                 | 700.0         |
    When I click on "Filters" "button"
    And I set the following fields in the "Question text" "core_reportbuilder > Filter" to these values:
      | Question text operator | Contains   |
      | Question text value    | Question 1 |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    Then the following should exist in the "Statistics" table:
      | Order | Question type   | Question text | Total responses | Correct responses | Average score |
      | 1     | Multiple choice | Question 1    | 2               | 1                 | 450.0         |
    And I should not see "Question 2"

  Scenario: Teacher views participant answers
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I follow "View results"
    And I click on "View participants" "link" in the "Round 1" "mod_kahoodle > round result"
    And I press "View answers" action in the "Sam" report row
    Then the following should exist in the "Participant answers" table:
      | Order | Question type   | Question text | Response | Correct! | Score | Response time |
      | 1     | Multiple choice | Question 1    | Option B | Yes      | 900   | 5.0 seconds   |
      | 2     | Multiple choice | Question 2    | Yes      | Yes      | 800   | 5.0 seconds   |
    When I click on "Filters" "button"
    And I set the following fields in the "Question text" "core_reportbuilder > Filter" to these values:
      | Question text operator | Contains   |
      | Question text value    | Question 1 |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    Then the following should exist in the "Participant answers" table:
      | Order | Question type   | Question text | Response | Correct! | Score | Response time |
      | 1     | Multiple choice | Question 1    | Option B | Yes      | 900   | 5.0 seconds   |
    And I should not see "Question 2"

  Scenario: Teacher plays back all stages from statistics report
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I follow "View results"
    And I click on "View statistics" "link" in the "Round 1" "mod_kahoodle > round result"
    And I click on "Play all" "link"
    And I wait until ".mod_kahoodle-overlay" "css_element" exists
    # Play all starts at the lobby stage with participants.
    Then "[data-stage='lobby']" "css_element" should exist
    And I should see "Sam" in the ".mod_kahoodle-overlay [data-stage='lobby']" "css_element"
    And I should see "Alex" in the ".mod_kahoodle-overlay [data-stage='lobby']" "css_element"
    # Advance to question 1 preview.
    When I click on "[data-action='next']" "css_element"
    And I wait until "[data-stage='preview']" "css_element" exists
    Then I should see "Question 1" in the ".mod_kahoodle-overlay [data-stage='preview']" "css_element"
    And I should see "1 of 2" in the ".mod_kahoodle-overlay" "css_element"
    # Advance to question 1 with answer options.
    When I click on "[data-action='next']" "css_element"
    And I wait until "[data-stage='question']" "css_element" exists
    Then I should see "Option A" in the ".mod_kahoodle-overlay [data-stage='question']" "css_element"
    And I should see "Option B" in the ".mod_kahoodle-overlay [data-stage='question']" "css_element"
    # Advance to question 1 results with correct answer marked.
    When I click on "[data-action='next']" "css_element"
    And I wait until "[data-stage='results']" "css_element" exists
    Then I should see "Option B" in the ".mod_kahoodle-overlay [data-stage='results'] .mod_kahoodle-option-correct" "css_element"
    # Advance to leaderboard after question 1.
    When I click on "[data-action='next']" "css_element"
    And I wait until "[data-stage='leaders']" "css_element" exists
    Then I should see "Leaderboard" in the ".mod_kahoodle-overlay [data-stage='leaders']" "css_element"
    And I should see "Sam" in the ".mod_kahoodle-overlay [data-stage='leaders'] .mod_kahoodle-leaderboard-row:nth-child(1)" "css_element"
    And I should see "Alex" in the ".mod_kahoodle-overlay [data-stage='leaders'] .mod_kahoodle-leaderboard-row:nth-child(2)" "css_element"
    # Advance through question 2 stages (preview, question, results) to revision.
    When I click on "[data-action='next']" "css_element"
    And I wait until "[data-stage='preview']" "css_element" exists
    Then I should see "2 of 2" in the ".mod_kahoodle-overlay" "css_element"
    When I click on "[data-action='next']" "css_element"
    And I wait until "[data-stage='question']" "css_element" exists
    And I click on "[data-action='next']" "css_element"
    And I wait until "[data-stage='results']" "css_element" exists
    And I click on "[data-action='next']" "css_element"
    And I wait until "[data-stage='revision']" "css_element" exists
    Then I should see "Sam" in the ".mod_kahoodle-overlay [data-stage='revision']" "css_element"
    # Close the playback overlay.
    When I click on "[data-action='close']" "css_element"
    Then ".mod_kahoodle-overlay" "css_element" should not exist

  Scenario: Teacher starts playback from specific question in statistics report
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I follow "View results"
    And I click on "View statistics" "link" in the "Round 1" "mod_kahoodle > round result"
    # Click the Playback action on the Question 2 row.
    And I press "Playback" action in the "Question 2" report row
    And I wait until ".mod_kahoodle-overlay" "css_element" exists
    # Playback should start at question 2 preview, showing "2 of 2".
    Then "[data-stage='preview']" "css_element" should exist
    And I should see "Question 2" in the ".mod_kahoodle-overlay [data-stage='preview']" "css_element"
    And I should see "2 of 2" in the ".mod_kahoodle-overlay" "css_element"
    # Close the overlay.
    When I click on "[data-action='close']" "css_element"
    Then ".mod_kahoodle-overlay" "css_element" should not exist

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
