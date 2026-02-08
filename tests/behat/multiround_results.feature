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

  Scenario: Teacher views participants for each round
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I follow "View results"
    # Round 2: Bob is rank 1, Carol is rank 2.
    And I click on "View participants" "link" in the "Round 2" "mod_kahoodle > round result"
    Then the following should exist in the "Participants" table:
      | Participant | First name  | Rank | Score | Correct answers | Questions answered |
      | Bob         | Bob Brown   | 1    | 837   | 1               | 1                  |
      | Carol       | Carol Clark | 2    | 513   | 1               | 2                  |
    # Round 1: Different students with different scores.
    When I click on "Back" "link" in the "div[role='main']" "css_element"
    And I click on "View participants" "link" in the "Round 1" "mod_kahoodle > round result"
    Then the following should exist in the "Participants" table:
      | Participant | First name  | Rank | Score | Correct answers | Questions answered |
      | Sam         | Sam Student | 1    | 1,732 | 2               | 2                  |
      | Alex        | Alex Adams  | 2    | 614   | 1               | 2                  |

  Scenario: Teacher views statistics for each round
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I follow "View results"
    # Round 2 statistics: Bob got Q1 right, Carol got Q2 right, Bob skipped Q2.
    And I click on "View statistics" "link" in the "Round 2" "mod_kahoodle > round result"
    Then the following should exist in the "Statistics" table:
      | Order | Question type   | Question text | Total responses | Correct responses | Average score |
      | 1     | Multiple choice | Question 1    | 2               | 1                 | 418.5         |
      | 2     | Multiple choice | Question 2    | 1               | 1                 | 256.5         |
    # Round 1 statistics: different response counts and averages.
    When I click on "Back" "link" in the "div[role='main']" "css_element"
    And I click on "View statistics" "link" in the "Round 1" "mod_kahoodle > round result"
    Then the following should exist in the "Statistics" table:
      | Order | Question type   | Question text | Total responses | Correct responses | Average score |
      | 1     | Multiple choice | Question 1    | 2               | 1                 | 456.5         |
      | 2     | Multiple choice | Question 2    | 2               | 2                 | 716.5         |

  Scenario: Teacher views participant answers with missing response
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I follow "View results"
    And I click on "View participants" "link" in the "Round 2" "mod_kahoodle > round result"
    And I press "View answers" action in the "Bob" report row
    Then the following should exist in the "Participant answers" table:
      | Order | Question type   | Question text | Response | Correct!  | Score | Response time |
      | 1     | Multiple choice | Question 1    | Option B | Yes       | 837   | 5.0 seconds   |
      | 2     | Multiple choice | Question 2    |          | No answer | 0     | -             |

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

  Scenario: Teacher views all rounds statistics report
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I follow "View results"
    And I follow "All rounds: Statistics"
    Then the following should exist in the "All rounds statistics" table:
      | Order | Question type   | Question text | Total participants | Total responses | Correct responses | Average score |
      | 1     | Multiple choice | Question 1    | 4                  | 4               | 2                 | 437.5         |
      | 2     | Multiple choice | Question 2    | 4                  | 3               | 3                 | 486.5         |

  Scenario: Modified question text appears in all rounds statistics but not per-round
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I follow "View results"
    # Edit Question 1 text from round 2's question management page.
    And I click on "Manage questions" "link" in the "Round 2" "mod_kahoodle > round result"
    And I click on "Actions" "link" in the "Question 1" "table_row"
    And I choose "Edit question" in the open action menu
    And I set the field "Question text" in the "Edit question" "dialogue" to "Modified Q1"
    And I click on "Save changes" "button" in the "Edit question" "dialogue"
    Then I should see "Modified Q1"
    And I should not see "Question 1"
    # All rounds: Statistics shows the latest version text with combined stats from both rounds.
    When I am on the "Test Kahoodle" "kahoodle activity" page
    And I follow "View results"
    And I follow "All rounds: Statistics"
    Then the following should exist in the "All rounds statistics" table:
      | Order | Question type   | Question text | Total participants | Total responses | Correct responses | Average score |
      | 1     | Multiple choice | Modified Q1   | 4                  | 4               | 2                 | 437.5         |
      | 2     | Multiple choice | Question 2    | 4                  | 3               | 3                 | 486.5         |
    # Round 1 Statistics still shows the original question text.
    When I click on "Back" "link" in the "div[role='main']" "css_element"
    And I click on "View statistics" "link" in the "Round 1" "mod_kahoodle > round result"
    Then the following should exist in the "Statistics" table:
      | Order | Question type   | Question text | Total responses | Correct responses | Average score |
      | 1     | Multiple choice | Question 1    | 2               | 1                 | 456.5         |
      | 2     | Multiple choice | Question 2    | 2               | 2                 | 716.5         |
