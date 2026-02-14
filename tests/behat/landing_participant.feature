@mod @mod_kahoodle @javascript
Feature: Landing page for participants
  As a student
  I see appropriate content on the Kahoodle landing page based on the game state

  Background:
    Given the following config values are set as admin:
      | requesttimeout | 2 | realtimeplugin_phppoll |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Sam       | Student  | student1@example.com |
      | teacher1 | Terry     | Teacher  | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | teacher1 | C1     | editingteacher |

  Scenario: Student sees waiting message when round is in preparation
    Given the following "activities" exist:
      | activity | name          | course | idnumber  |
      | kahoodle | Test Kahoodle | C1     | kahoodle1 |
    And the following "mod_kahoodle > questions" exist:
      | kahoodle      | questiontext | questionconfig |
      | Test Kahoodle | Question 1   | A\n*B\nC       |
    When I log in as "student1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I wait until ".mod-kahoodle-landing" "css_element" exists
    Then I should see "The activity has not started yet"
    And I should not see "The activity has finished"
    And I should not see "Join as"
    And I should not see "You scored"

  Scenario: Student sees finished message when round is archived
    Given the following "activities" exist:
      | activity | name          | course | idnumber  |
      | kahoodle | Test Kahoodle | C1     | kahoodle1 |
    And the following "mod_kahoodle > questions" exist:
      | kahoodle      | questiontext | questionconfig |
      | Test Kahoodle | Question 1   | A\n*B\nC       |
    And the kahoodle "Test Kahoodle" round stage is "archived"
    When I log in as "student1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I wait until "The activity has finished." "text" exists
    Then I should not see "The activity has not started yet"
    And I should not see "You scored"

  Scenario: Student sees join form when round is in progress
    Given the following "activities" exist:
      | activity | name          | course | idnumber  |
      | kahoodle | Test Kahoodle | C1     | kahoodle1 |
    And the following "mod_kahoodle > questions" exist:
      | kahoodle      | questiontext | questionconfig |
      | Test Kahoodle | Question 1   | A\n*B\nC       |
    And the kahoodle "Test Kahoodle" round stage is "lobby"
    When I log in as "student1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I wait until ".mod-kahoodle-landing" "css_element" exists
    Then I should see "Join as"
    And I should not see "The activity has not started yet"
    And I should not see "The activity has finished"
    And I should not see "You scored"
    # Archive the round to stop any pending requests before the test ends.
    And the kahoodle "Test Kahoodle" round stage is "archived"

  Scenario: Student currently participating sees resume controls
    Given the following "activities" exist:
      | activity | name          | course | idnumber  |
      | kahoodle | Test Kahoodle | C1     | kahoodle1 |
    And the following "mod_kahoodle > questions" exist:
      | kahoodle      | questiontext | questionconfig |
      | Test Kahoodle | Question 1   | A\n*B\nC       |
    And the kahoodle "Test Kahoodle" round stage is "lobby"
    And the following "mod_kahoodle > participants" exist:
      | kahoodle      | user     | displayname |
      | Test Kahoodle | student1 | Sam         |
    When I log in as "student1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    # The participant overlay opens automatically; close it to see the landing page.
    And I wait until ".mod_kahoodle-overlay [data-action='close']" "css_element" exists
    And I click on "[data-action='close']" "css_element"
    And I should see "The game is on!"
    And I should see "Resume playing"
    And I should not see "The activity has finished"
    And I should not see "You scored"
    # Archive the round to stop realtime polling before the test ends.
    When the kahoodle "Test Kahoodle" round stage is "archived"
    And I wait until "The activity has finished." "text" exists

  Scenario: Student with past participation sees finished and score when round is archived (no repeat)
    Given the following "activities" exist:
      | activity | name          | course | idnumber  | allowrepeat |
      | kahoodle | Test Kahoodle | C1     | kahoodle1 | 0           |
    And the following "mod_kahoodle > questions" exist:
      | kahoodle      | questiontext | questionconfig |
      | Test Kahoodle | Question 1   | A\n*B\nC       |
    And the kahoodle "Test Kahoodle" round stage is "lobby"
    And the following "mod_kahoodle > participants" exist:
      | kahoodle      | user     | displayname |
      | Test Kahoodle | student1 | Sam         |
    And the following "mod_kahoodle > responses" exist:
      | kahoodle      | user     | question | response | iscorrect | points |
      | Test Kahoodle | student1 | 1        | 2        | 1         | 750    |
    And the kahoodle "Test Kahoodle" round stage is "archived"
    When I log in as "student1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I wait until ".mod-kahoodle-landing" "css_element" exists
    Then I should see "The activity has finished."
    And I should see "You scored 750 points"

  Scenario: Student with past participation sees finished when new round is in preparation (no repeat)
    Given the following "activities" exist:
      | activity | name          | course | idnumber  | allowrepeat |
      | kahoodle | Test Kahoodle | C1     | kahoodle1 | 0           |
    And the following "mod_kahoodle > questions" exist:
      | kahoodle      | questiontext | questionconfig |
      | Test Kahoodle | Question 1   | A\n*B\nC       |
    And the kahoodle "Test Kahoodle" round stage is "lobby"
    And the following "mod_kahoodle > participants" exist:
      | kahoodle      | user     | displayname |
      | Test Kahoodle | student1 | Sam         |
    And the following "mod_kahoodle > responses" exist:
      | kahoodle      | user     | question | response | iscorrect | points |
      | Test Kahoodle | student1 | 1        | 2        | 1         | 750    |
    And the kahoodle "Test Kahoodle" round stage is "archived"
    And a new round is prepared for the kahoodle "Test Kahoodle"
    When I log in as "student1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I wait until ".mod-kahoodle-landing" "css_element" exists
    Then I should see "The activity has finished."
    And I should see "You scored 750 points"
    And I should not see "The activity has not started yet"
    And I should not see "Join as"

  Scenario: Student with past participation sees finished when new round is in progress (no repeat)
    Given the following "activities" exist:
      | activity | name          | course | idnumber  | allowrepeat |
      | kahoodle | Test Kahoodle | C1     | kahoodle1 | 0           |
    And the following "mod_kahoodle > questions" exist:
      | kahoodle      | questiontext | questionconfig |
      | Test Kahoodle | Question 1   | A\n*B\nC       |
    And the kahoodle "Test Kahoodle" round stage is "lobby"
    And the following "mod_kahoodle > participants" exist:
      | kahoodle      | user     | displayname |
      | Test Kahoodle | student1 | Sam         |
    And the following "mod_kahoodle > responses" exist:
      | kahoodle      | user     | question | response | iscorrect | points |
      | Test Kahoodle | student1 | 1        | 2        | 1         | 750    |
    And the kahoodle "Test Kahoodle" round stage is "archived"
    And a new round is prepared for the kahoodle "Test Kahoodle"
    And the kahoodle "Test Kahoodle" round stage is "lobby"
    When I log in as "student1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I wait until ".mod-kahoodle-landing" "css_element" exists
    Then I should see "The activity has finished."
    And I should see "You scored 750 points"
    And I should not see "Join as"
    # Archive the round to stop any pending requests before the test ends.
    And the kahoodle "Test Kahoodle" round stage is "archived"

  Scenario: Student with past participation sees waiting when new round is in preparation (allow repeat)
    Given the following "activities" exist:
      | activity | name          | course | idnumber  | allowrepeat |
      | kahoodle | Test Kahoodle | C1     | kahoodle1 | 1           |
    And the following "mod_kahoodle > questions" exist:
      | kahoodle      | questiontext | questionconfig |
      | Test Kahoodle | Question 1   | A\n*B\nC       |
    And the kahoodle "Test Kahoodle" round stage is "lobby"
    And the following "mod_kahoodle > participants" exist:
      | kahoodle      | user     | displayname |
      | Test Kahoodle | student1 | Sam         |
    And the following "mod_kahoodle > responses" exist:
      | kahoodle      | user     | question | response | iscorrect | points |
      | Test Kahoodle | student1 | 1        | 2        | 1         | 750    |
    And the kahoodle "Test Kahoodle" round stage is "archived"
    And a new round is prepared for the kahoodle "Test Kahoodle"
    When I log in as "student1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I wait until ".mod-kahoodle-landing" "css_element" exists
    Then I should see "The activity has not started yet"
    And I should see "You scored 750 points"
    And I should not see "The activity has finished"
    And I should not see "Join as"

  Scenario: Student with past participation sees join form when new round is in progress (allow repeat)
    Given the following "activities" exist:
      | activity | name          | course | idnumber  | allowrepeat |
      | kahoodle | Test Kahoodle | C1     | kahoodle1 | 1           |
    And the following "mod_kahoodle > questions" exist:
      | kahoodle      | questiontext | questionconfig |
      | Test Kahoodle | Question 1   | A\n*B\nC       |
    And the kahoodle "Test Kahoodle" round stage is "lobby"
    And the following "mod_kahoodle > participants" exist:
      | kahoodle      | user     | displayname |
      | Test Kahoodle | student1 | Sam         |
    And the following "mod_kahoodle > responses" exist:
      | kahoodle      | user     | question | response | iscorrect | points |
      | Test Kahoodle | student1 | 1        | 2        | 1         | 750    |
    And the kahoodle "Test Kahoodle" round stage is "archived"
    And a new round is prepared for the kahoodle "Test Kahoodle"
    And the kahoodle "Test Kahoodle" round stage is "lobby"
    When I log in as "student1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I wait until ".mod-kahoodle-landing" "css_element" exists
    Then I should see "Join as"
    And I should see "You have already played this activity."
    And I should see "You scored 750 points"
    And I should not see "The activity has finished"
    And I should not see "The activity has not started yet"
    # Archive the round to stop any pending requests before the test ends.
    And the kahoodle "Test Kahoodle" round stage is "archived"
