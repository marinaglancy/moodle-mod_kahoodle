@mod @mod_kahoodle @javascript
Feature: Anonymous mode reports
  As a teacher
  I can view results in fully anonymous mode without seeing participant identities

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Terry     | Teacher  | teacher1@example.com |
      | student1 | Sam       | Student  | student1@example.com |
      | student2 | Alex      | Adams    | student2@example.com |
      | student3 | Bob       | Brown    | student3@example.com |
      | student4 | Carol     | Clark    | student4@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | student3 | C1     | student        |
      | student4 | C1     | student        |
    And the following "activities" exist:
      | activity | name          | course | idnumber  | identitymode |
      | kahoodle | Test Kahoodle | C1     | kahoodle1 | 3            |
    And the following "mod_kahoodle > questions" exist:
      | kahoodle      | questiontext | questionconfig      |
      | Test Kahoodle | Question 1   | Option A\n*Option B |
      | Test Kahoodle | Question 2   | *Yes\nNo            |
    # Round 1: Two anonymous participants with scores.
    And the kahoodle "Test Kahoodle" round stage is "lobby"
    And the following "mod_kahoodle > participants" exist:
      | kahoodle      | user     | displayname | totalscore |
      | Test Kahoodle | student1 | PlayerOne   | 1732       |
      | Test Kahoodle | student2 | PlayerTwo   | 614        |
    And the following "mod_kahoodle > responses" exist:
      | kahoodle      | user     | question | response | iscorrect | points |
      | Test Kahoodle | student1 | 1        | 2        | 1         | 913    |
      | Test Kahoodle | student1 | 2        | 1        | 1         | 819    |
      | Test Kahoodle | student2 | 1        | 1        | 0         | 0      |
      | Test Kahoodle | student2 | 2        | 1        | 1         | 614    |
    And the kahoodle "Test Kahoodle" round stage is "archived"
    # Create round 2 with different anonymous participants.
    And I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I follow "Prepare new round"
    And I log out
    And the kahoodle "Test Kahoodle" round stage is "lobby"
    And the following "mod_kahoodle > participants" exist:
      | kahoodle      | user     | displayname | totalscore |
      | Test Kahoodle | student3 | PlayerThree | 837        |
      | Test Kahoodle | student4 | PlayerFour  | 513        |
    And the following "mod_kahoodle > responses" exist:
      | kahoodle      | user     | question | response | iscorrect | points |
      | Test Kahoodle | student3 | 1        | 2        | 1         | 837    |
      | Test Kahoodle | student4 | 1        | 1        | 0         | 0      |
      | Test Kahoodle | student4 | 2        | 1        | 1         | 513    |
    And the kahoodle "Test Kahoodle" round stage is "archived"

  Scenario: Teacher views all rounds participants report in anonymous mode
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I follow "View results"
    And I follow "All rounds: Participants"
    # No "First name" column in anonymous mode.
    Then I should not see "First name"
    And the following should exist in the "All rounds participants" table:
      | Round   | Participant | Rank | Score | Correct answers | Questions answered |
      | Round 1 | PlayerOne   | 1    | 1,732 | 2               | 2                  |
      | Round 2 | PlayerThree | 1    | 837   | 1               | 1                  |
      | Round 1 | PlayerTwo   | 2    | 614   | 1               | 2                  |
      | Round 2 | PlayerFour  | 2    | 513   | 1               | 2                  |

  Scenario: Teacher views participants after changing to non-anonymous mode
    Given the following config values are set as admin:
      | disablekahoodleplus | 1 |
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity editing" page
    And I set the field "Participant identity" to "Required alias"
    And I press "Save and return to course"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I follow "View results"
    And I follow "All rounds: Participants"
    # "First name" column is now present but empty since participants were anonymous.
    Then the following should exist in the "All rounds participants" table:
      | Round   | Participant | First name | Rank | Score | Correct answers | Questions answered |
      | Round 1 | PlayerOne   |            | 1    | 1,732 | 2               | 2                  |
      | Round 1 | PlayerTwo   |            | 2    | 614   | 1               | 2                  |
    # User real names should not appear since participants were created anonymously.
    And I should not see "Sam Student"
    And I should not see "Alex Adams"
