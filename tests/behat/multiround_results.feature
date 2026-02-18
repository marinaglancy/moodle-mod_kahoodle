@mod @mod_kahoodle @javascript
Feature: Multi-round results
  As a teacher
  I can view results across multiple rounds with different participants and scores

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
      | activity | name          | course | idnumber  |
      | kahoodle | Test Kahoodle | C1     | kahoodle1 |
    And the following "mod_kahoodle > questions" exist:
      | kahoodle      | questiontext | questionconfig      |
      | Test Kahoodle | Question 1   | Option A\n*Option B |
      | Test Kahoodle | Question 2   | *Yes\nNo            |
    # Round 1: Sam scores higher (rank 1), both answer all questions.
    And the kahoodle "Test Kahoodle" round stage is "lobby"
    And the following "mod_kahoodle > participants" exist:
      | kahoodle      | user     | displayname | totalscore |
      | Test Kahoodle | student1 | Sam         | 1732       |
      | Test Kahoodle | student2 | Alex        | 614        |
    And the following "mod_kahoodle > responses" exist:
      | kahoodle      | user     | question | response | iscorrect | points |
      | Test Kahoodle | student1 | 1        | 2        | 1         | 913    |
      | Test Kahoodle | student1 | 2        | 1        | 1         | 819    |
      | Test Kahoodle | student2 | 1        | 1        | 0         | 0      |
      | Test Kahoodle | student2 | 2        | 1        | 1         | 614    |
    And the kahoodle "Test Kahoodle" round stage is "archived"
    # Create round 2 by pressing the button.
    And I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I follow "Prepare new round"
    And I log out
    # Round 2: Different students. Bob scores higher (rank 1), Bob does not answer Question 2.
    And the kahoodle "Test Kahoodle" round stage is "lobby"
    And the following "mod_kahoodle > participants" exist:
      | kahoodle      | user     | displayname | totalscore |
      | Test Kahoodle | student3 | Bob         | 837        |
      | Test Kahoodle | student4 | Carol       | 513        |
    And the following "mod_kahoodle > responses" exist:
      | kahoodle      | user     | question | response | iscorrect | points |
      | Test Kahoodle | student3 | 1        | 2        | 1         | 837    |
      | Test Kahoodle | student4 | 1        | 1        | 0         | 0      |
      | Test Kahoodle | student4 | 2        | 1        | 1         | 513    |
    And the kahoodle "Test Kahoodle" round stage is "archived"

  Scenario: Teacher views all rounds participants report
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I follow "View results"
    And I follow "All rounds: Participants"
    Then the following should exist in the "All rounds participants" table:
      | Round   | Participant | First name  | Rank | Score | Correct answers | Questions answered |
      | Round 1 | Sam         | Sam Student | 1    | 1,732 | 2               | 2                  |
      | Round 2 | Bob         | Bob Brown   | 1    | 837   | 1               | 1                  |
      | Round 1 | Alex        | Alex Adams  | 2    | 614   | 1               | 2                  |
      | Round 2 | Carol       | Carol Clark | 2    | 513   | 1               | 2                  |
