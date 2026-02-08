@mod @mod_kahoodle @javascript
Feature: Facilitator game control
  As a teacher
  I can start, control, and finish a Kahoodle game

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Terry     | Teacher  | teacher1@example.com |
      | student1 | Sam       | Student  | student1@example.com |
      | student2 | Alex      | Student  | student2@example.com |
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
      | kahoodle      | questiontext                   | questionconfig         | image |
      | Test Kahoodle | What is the capital of France? | London\n*Paris\nBerlin | 1     |
      | Test Kahoodle | What is 2 + 2?                 | 3\n*4\n5               |       |

  Scenario: Teacher sees preparation controls on landing page
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    Then I should see "Allow participants to join"
    And I should see "Manage questions"

  Scenario: Teacher starts game and sees lobby in overlay
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I follow "Allow participants to join"
    And I wait until ".mod_kahoodle-overlay" "css_element" exists
    # Lobby overlay shows quiz title in header and QR code for joining.
    Then I should see "Test Kahoodle" in the ".mod_kahoodle-game-title" "css_element"
    And "[data-stage='lobby']" "css_element" should exist
    And ".mod_kahoodle-qr-code" "css_element" should exist
    # Participants join and their names appear in the lobby.
    When "student1" joins the kahoodle "Test Kahoodle"
    Then I should see "Sam Student" in the ".mod_kahoodle-participants-list" "css_element"
    When "student2" joins the kahoodle "Test Kahoodle"
    Then I should see "Alex Student" in the ".mod_kahoodle-participants-list" "css_element"

  Scenario: Teacher advances through question 1 stages using next button
    Given the kahoodle "Test Kahoodle" round stage is "lobby"
    And the following "mod_kahoodle > participants" exist:
      | kahoodle      | user     | displayname |
      | Test Kahoodle | student1 | Sam         |
      | Test Kahoodle | student2 | Alex        |
    And the following "mod_kahoodle > responses" exist:
      | kahoodle      | user     | question | response | iscorrect | points |
      | Test Kahoodle | student1 | 1        | 2        | 1         | 900    |
      | Test Kahoodle | student2 | 1        | 1        | 0         | 0      |
    And I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I wait until ".mod_kahoodle-overlay" "css_element" exists
    # Lobby stage: see participants who joined.
    Then I should see "Sam" in the ".mod_kahoodle-overlay" "css_element"
    And I should see "Alex" in the ".mod_kahoodle-overlay" "css_element"
    # Advance to question preview: shows question text but no image.
    When I click on "[data-action='next']" "css_element"
    Then I should see "What is the capital of France?" in the ".mod_kahoodle-overlay" "css_element"
    And I should see "1 of 2" in the ".mod_kahoodle-overlay" "css_element"
    And ".mod_kahoodle-image-container" "css_element" should not exist
    # Advance to question: shows question text, image, and answer options.
    When I click on "[data-action='next']" "css_element"
    Then I should see "What is the capital of France?" in the ".mod_kahoodle-overlay" "css_element"
    And ".mod_kahoodle-image-container img" "css_element" should exist
    And I should see "London" in the ".mod_kahoodle-overlay" "css_element"
    And I should see "Paris" in the ".mod_kahoodle-overlay" "css_element"
    And I should see "Berlin" in the ".mod_kahoodle-overlay" "css_element"
    # Advance to question results with correct answer marked.
    When I click on "[data-action='next']" "css_element"
    Then I should see "Paris" in the ".mod_kahoodle-option-correct" "css_element"
    # Advance to leaders with leaderboard: Sam (900) ranked above Alex (0).
    When I click on "[data-action='next']" "css_element"
    Then I should see "Leaderboard" in the ".mod_kahoodle-overlay" "css_element"
    And I should see "Sam" in the ".mod_kahoodle-leaderboard-row:nth-child(1)" "css_element"
    And I should see "900" in the ".mod_kahoodle-leaderboard-row:nth-child(1)" "css_element"
    And I should see "Alex" in the ".mod_kahoodle-leaderboard-row:nth-child(2)" "css_element"
    And I should see "0" in the ".mod_kahoodle-leaderboard-row:nth-child(2)" "css_element"

  Scenario: Teacher sees question 2 content after advancing with behat step
    Given the kahoodle "Test Kahoodle" round stage is "lobby"
    And the following "mod_kahoodle > participants" exist:
      | kahoodle      | user     | displayname |
      | Test Kahoodle | student1 | Sam         |
      | Test Kahoodle | student2 | Alex        |
    And the following "mod_kahoodle > responses" exist:
      | kahoodle      | user     | question | response | iscorrect | points |
      | Test Kahoodle | student1 | 1        | 2        | 1         | 900    |
      | Test Kahoodle | student2 | 1        | 1        | 0         | 0      |
      | Test Kahoodle | student1 | 2        | 2        | 1         | 850    |
      | Test Kahoodle | student2 | 2        | 1        | 0         | 0      |
    And the kahoodle "Test Kahoodle" round stage is "preview-2"
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I wait until ".mod_kahoodle-overlay" "css_element" exists
    Then I should see "What is 2 + 2?" in the ".mod_kahoodle-overlay" "css_element"
    And I should see "2 of 2" in the ".mod_kahoodle-overlay" "css_element"

  Scenario: Teacher sees revision stage with leaderboard and finish button
    Given the kahoodle "Test Kahoodle" round stage is "lobby"
    And the following "mod_kahoodle > participants" exist:
      | kahoodle      | user     | displayname |
      | Test Kahoodle | student1 | Sam         |
      | Test Kahoodle | student2 | Alex        |
    And the following "mod_kahoodle > responses" exist:
      | kahoodle      | user     | question | response | iscorrect | points |
      | Test Kahoodle | student1 | 1        | 2        | 1         | 900    |
      | Test Kahoodle | student2 | 1        | 1        | 0         | 0      |
    And the kahoodle "Test Kahoodle" round stage is "revision"
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I wait until ".mod_kahoodle-overlay" "css_element" exists
    Then "[data-stage='revision']" "css_element" should exist
    # TODO podium animation prevents checking leaderboard atm
    # And I should see "Sam" in the ".mod_kahoodle-overlay" "css_element"
    And I should see "Finish activity" in the ".mod_kahoodle-overlay" "css_element"

  Scenario: Teacher finishes game from landing page
    Given the kahoodle "Test Kahoodle" round stage is "lobby"
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I wait until ".mod_kahoodle-overlay" "css_element" exists
    # Close the facilitator overlay to return to the landing page.
    And I click on "[data-action='close']" "css_element"
    And I click on "End round for everyone" "button"
    And I click on "Yes" "button" in the "Finish activity" "dialogue"
    Then I should see "The activity has finished."
    And I should see "View results"
    And I should see "Prepare new round"

  Scenario: Teacher creates new round from archived state
    Given the kahoodle "Test Kahoodle" round stage is "lobby"
    Given the kahoodle "Test Kahoodle" round stage is "archived"
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    Then I should see "The activity has finished."
    When I follow "Prepare new round"
    Then I should see "Allow participants to join"
    And I should see "Manage questions"

  Scenario: Teacher sees auto advance and can pause and resume
    # Create a separate activity with a short preview duration for timing tests.
    Given the following "activities" exist:
      | activity | name          | course | idnumber  | questionpreviewduration |
      | kahoodle | Fast Kahoodle | C1     | kahoodle2 | 3                       |
    And the following "mod_kahoodle > questions" exist:
      | kahoodle      | questiontext           | questionconfig    |
      | Fast Kahoodle | Which colour is warm?  | *Red\nBlue\nGreen |
      | Fast Kahoodle | Which animal says moo? | Dog\n*Cow         |
    And the kahoodle "Fast Kahoodle" round stage is "lobby"
    And the following "mod_kahoodle > participants" exist:
      | kahoodle      | user     | displayname |
      | Fast Kahoodle | student1 | Sam         |
    And the following "mod_kahoodle > responses" exist:
      | kahoodle      | user     | question | response | iscorrect | points |
      | Fast Kahoodle | student1 | 1        | 1        | 1         | 900    |
    And I log in as "teacher1"
    And I am on the "Fast Kahoodle" "kahoodle activity" page
    And I wait until ".mod_kahoodle-overlay" "css_element" exists
    # Advance to preview-1 and verify we are on the preview stage.
    When I click on "[data-action='next']" "css_element"
    Then "[data-stage='preview']" "css_element" should exist
    Then "[data-stage='question']" "css_element" should not exist
    # Wait for auto-advance (preview duration is 3s, wait 5s for buffer).
    When I wait "5" seconds
    Then "[data-stage='preview']" "css_element" should not exist
    Then "[data-stage='question']" "css_element" should exist
    # Advance to preview-2 and pause before auto-advance triggers.
    When the kahoodle "Fast Kahoodle" round stage is "preview-2"
    And I wait until "[data-stage='preview']" "css_element" exists
    Then "[data-stage='question']" "css_element" should not exist
    And I click on "[data-action='pause']" "css_element"
    # After 5 seconds, should still be on preview (paused).
    And I wait "5" seconds
    Then "[data-stage='preview']" "css_element" should exist
    Then "[data-stage='question']" "css_element" should not exist
    # Resume and wait for auto-advance to question stage.
    When I click on "[data-action='resume']" "css_element"
    And I wait "5" seconds
    Then "[data-stage='preview']" "css_element" should not exist
    Then "[data-stage='question']" "css_element" should exist
