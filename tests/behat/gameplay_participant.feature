@mod @mod_kahoodle @javascript
Feature: Participant gameplay
  As a student
  I can join a game and see question content as the game progresses

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Sam       | Student  | student1@example.com |
      | student2 | Alex      | Student  | student2@example.com |
      | teacher1 | Terry     | Teacher  | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity | name          | course | idnumber  |
      | kahoodle | Test Kahoodle | C1     | kahoodle1 |
    And the following "mod_kahoodle > questions" exist:
      | kahoodle      | questiontext   | questionconfig         |
      | Test Kahoodle | Capital of UK? | London\n*Paris\nBerlin |
      | Test Kahoodle | 2 + 2 = ?      | 3\n*4\n5               |
    And the kahoodle "Test Kahoodle" round stage is "lobby"
    And the following "mod_kahoodle > participants" exist:
      | kahoodle      | user     | displayname |
      | Test Kahoodle | student2 | Alex        |
    And the following "mod_kahoodle > responses" exist:
      | kahoodle      | user     | question | response | iscorrect | points |
      | Test Kahoodle | student2 | 1        | 2        | 1         | 950    |
    And I log in as "student1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I press "Join"
    And I should see "You are joining as" in the ".mod_kahoodle-participant-container" "css_element"

  Scenario: Student score updates after answering a question
    # Advance to question preview for first question - score should be 0.
    When the kahoodle "Test Kahoodle" round stage is "preview-1"
    And I wait until ".mod_kahoodle-participant-preview-content" "css_element" exists
    Then I should see "Question 1" in the ".mod_kahoodle-participant-preview-content" "css_element"
    And I should see "Get ready!" in the ".mod_kahoodle-participant-preview-content" "css_element"
    And I should see "0" in the ".mod_kahoodle-participant-result-total-value" "css_element"
    # Advance to question-1 and wait for question content to render.
    When the kahoodle "Test Kahoodle" round stage is "question-1"
    And I wait until ".mod_kahoodle-participant-question-content" "css_element" exists
    # Generate response with 900 points.
    And the following "mod_kahoodle > responses" exist:
      | kahoodle      | user     | question | response | iscorrect | points |
      | Test Kahoodle | student1 | 1        | 2        | 1         | 900    |
    And the kahoodle "Test Kahoodle" round stage is "preview-2"
    And I wait until ".mod_kahoodle-participant-preview-content" "css_element" exists
    Then I should see "Question 2" in the ".mod_kahoodle-participant-preview-content" "css_element"
    And I should see "900" in the ".mod_kahoodle-participant-result-total-value" "css_element"

  Scenario: Student selects correct answer and sees positive feedback
    When the kahoodle "Test Kahoodle" round stage is "question-1"
    And I wait until ".mod_kahoodle-participant-question-content" "css_element" exists
    # Click the correct answer (B = option 2, Paris).
    And I click on ".mod_kahoodle-option2" "css_element"
    And I should see "Waiting for results..." in the ".mod_kahoodle-participant-container" "css_element"
    # Advance to results stage.
    When the kahoodle "Test Kahoodle" round stage is "results-1"
    And I wait until ".mod_kahoodle-participant-result-content" "css_element" exists
    Then I should see "Correct!" in the ".mod_kahoodle-participant-result-content" "css_element"
    And ".mod_kahoodle-participant-result-score" "css_element" should exist

  Scenario: Student selects wrong answer and sees negative feedback
    When the kahoodle "Test Kahoodle" round stage is "question-1"
    And I wait until ".mod_kahoodle-participant-question-content" "css_element" exists
    # Click the wrong answer (A = option 1, London).
    And I click on ".mod_kahoodle-option1" "css_element"
    And I should see "Waiting for results..." in the ".mod_kahoodle-participant-container" "css_element"
    # Advance to results stage.
    When the kahoodle "Test Kahoodle" round stage is "results-1"
    And I wait until ".mod_kahoodle-participant-result-content" "css_element" exists
    Then I should see "Incorrect" in the ".mod_kahoodle-participant-result-content" "css_element"

  Scenario: Student does not answer and sees time up message
    When the kahoodle "Test Kahoodle" round stage is "question-1"
    And I wait until ".mod_kahoodle-participant-question-content" "css_element" exists
    # Don't click anything - advance directly to results.
    When the kahoodle "Test Kahoodle" round stage is "results-1"
    And I wait until ".mod_kahoodle-participant-result-content" "css_element" exists
    Then I should see "Time's up!" in the ".mod_kahoodle-participant-result-content" "css_element"

  Scenario: Student sees rank in leaders stage
    # Generate response for student1 with fewer points than student2 (950).
    When the kahoodle "Test Kahoodle" round stage is "question-1"
    And I wait until ".mod_kahoodle-participant-question-content" "css_element" exists
    And the following "mod_kahoodle > responses" exist:
      | kahoodle      | user     | question | response | iscorrect | points |
      | Test Kahoodle | student1 | 1        | 2        | 1         | 800    |
    # Advance to leaders stage - rankings are computed from responses.
    When the kahoodle "Test Kahoodle" round stage is "leaders-1"
    And I wait until ".mod_kahoodle-participant-result-content" "css_element" exists
    # Student1 (800pts) is behind student2 (950pts).
    Then I should see "You are in 2nd place" in the ".mod_kahoodle-participant-rank" "css_element"
    And I should see "150 points behind Alex" in the ".mod_kahoodle-participant-rank" "css_element"

  Scenario: Student sees final rank on revision screen
    # Generate response for student1 so rankings are meaningful.
    When the kahoodle "Test Kahoodle" round stage is "question-1"
    And I wait until ".mod_kahoodle-participant-question-content" "css_element" exists
    And the following "mod_kahoodle > responses" exist:
      | kahoodle      | user     | question | response | iscorrect | points |
      | Test Kahoodle | student1 | 1        | 2        | 1         | 800    |
    And the kahoodle "Test Kahoodle" round stage is "revision"
    And I wait until ".mod_kahoodle-participant-revision-content" "css_element" exists
    Then I should see "Drumroll" in the ".mod_kahoodle-participant-revision-content" "css_element"
    # Reveal 3rd place - student1 is 2nd so should still see drumroll.
    When the kahoodle "Test Kahoodle" rank "rank3" is revealed
    Then I should see "Drumroll" in the ".mod_kahoodle-participant-revision-content" "css_element"
    And I should not see "You finished in"
    # Reveal 2nd place - student1 is 2nd so should now see their final rank.
    When the kahoodle "Test Kahoodle" rank "rank2" is revealed
    Then I should see "You finished in 2nd place"

  Scenario: Student is redirected when game is archived
    When the kahoodle "Test Kahoodle" round stage is "question-1"
    And I wait until ".mod_kahoodle-participant-question-content" "css_element" exists
    # Facilitator finishes the game while student is mid-question.
    And the kahoodle "Test Kahoodle" round stage is "archived"
    Then I should see "The activity has finished."
