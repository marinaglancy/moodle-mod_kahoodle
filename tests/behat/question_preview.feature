@mod @mod_kahoodle @javascript
Feature: Previewing questions in Kahoodle
  In order to verify my quiz content before starting a game
  As a teacher
  I need to be able to preview questions from the question management page

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
      | activity | name          | course | idnumber  | questionpreviewduration | questionduration | questionresultsduration |
      | kahoodle | Test Kahoodle | C1     | kahoodle1 | 120                     | 120              | 120                     |
    And the following "mod_kahoodle > questions" exist:
      | kahoodle      | questiontext                   | questionconfig         | attachimage |
      | Test Kahoodle | What is the capital of France? | London\n*Paris\nBerlin | 1           |
      | Test Kahoodle | What is 2 + 2?                 | 3\n*4\n5               |             |
      | Test Kahoodle | What color is the sky?         | Red\nGreen\n*Blue      |             |

  Scenario: Preview a question and navigate through all stages
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I navigate to "Questions" in current page administration
    And I click on "Actions" "link" in the "What is the capital of France?" "table_row"
    And I choose "Preview question" in the open action menu
    And I wait until ".mod_kahoodle-overlay [data-stage='preview']" "css_element" exists
    # Preview stage shows question text and counter, but no image and no answer options.
    Then I should see "What is the capital of France?" in the ".mod_kahoodle-overlay [data-stage='preview']" "css_element"
    And I should see "1 of 3" in the ".mod_kahoodle-overlay" "css_element"
    And ".mod_kahoodle-image-container" "css_element" should not exist
    And I should not see "London"
    And I should not see "Paris"
    # Advance to question stage: shows question text, image, and answer options.
    When I click on "[data-action='next']" "css_element"
    And I wait until ".mod_kahoodle-overlay [data-stage='question']" "css_element" exists
    Then I should see "What is the capital of France?" in the ".mod_kahoodle-overlay [data-stage='question']" "css_element"
    And ".mod_kahoodle-image-container img" "css_element" should exist
    And I should see "London" in the ".mod_kahoodle-overlay [data-stage='question']" "css_element"
    And I should see "Paris" in the ".mod_kahoodle-overlay [data-stage='question']" "css_element"
    And I should see "Berlin" in the ".mod_kahoodle-overlay [data-stage='question']" "css_element"
    # Advance to results stage: shows correct answer marked.
    When I click on "[data-action='next']" "css_element"
    And I wait until ".mod_kahoodle-overlay [data-stage='results']" "css_element" exists
    Then I should see "Paris" in the ".mod_kahoodle-overlay [data-stage='results'] .mod_kahoodle-option-correct" "css_element"
    # Advance to next question preview.
    When I click on "[data-action='next']" "css_element"
    And I wait until ".mod_kahoodle-overlay [data-stage='preview']" "css_element" exists
    Then I should see "What is 2 + 2?" in the ".mod_kahoodle-overlay [data-stage='preview']" "css_element"
    And I should see "2 of 3" in the ".mod_kahoodle-overlay" "css_element"
    # Advance through question 2 stages.
    When I click on "[data-action='next']" "css_element"
    And I wait until ".mod_kahoodle-overlay [data-stage='question']" "css_element" exists
    Then I should see "3" in the ".mod_kahoodle-overlay [data-stage='question']" "css_element"
    And I should see "4" in the ".mod_kahoodle-overlay [data-stage='question']" "css_element"
    And I should see "5" in the ".mod_kahoodle-overlay [data-stage='question']" "css_element"
    And ".mod_kahoodle-image-container" "css_element" should not exist
    When I click on "[data-action='next']" "css_element"
    And I wait until ".mod_kahoodle-overlay [data-stage='results']" "css_element" exists
    Then I should see "4" in the ".mod_kahoodle-overlay [data-stage='results'] .mod_kahoodle-option-correct" "css_element"
    # Advance to question 3 preview.
    When I click on "[data-action='next']" "css_element"
    And I wait until ".mod_kahoodle-overlay [data-stage='preview']" "css_element" exists
    Then I should see "What color is the sky?" in the ".mod_kahoodle-overlay [data-stage='preview']" "css_element"
    And I should see "3 of 3" in the ".mod_kahoodle-overlay" "css_element"

  Scenario: Navigate back through preview stages
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I navigate to "Questions" in current page administration
    And I click on "Actions" "link" in the "What is the capital of France?" "table_row"
    And I choose "Preview question" in the open action menu
    And I wait until ".mod_kahoodle-overlay [data-stage='preview']" "css_element" exists
    Then I should see "What is the capital of France?"
    # Go forward to question stage.
    When I click on "[data-action='next']" "css_element"
    And I wait until ".mod_kahoodle-overlay [data-stage='question']" "css_element" exists
    # Go back to preview stage.
    When I click on "[data-action='back']" "css_element"
    And I wait until ".mod_kahoodle-overlay [data-stage='preview']" "css_element" exists
    Then I should see "What is the capital of France?" in the ".mod_kahoodle-overlay [data-stage='preview']" "css_element"
    And I should see "1 of 3" in the ".mod_kahoodle-overlay" "css_element"

  Scenario: Close preview overlay
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I navigate to "Questions" in current page administration
    And I click on "Actions" "link" in the "What is the capital of France?" "table_row"
    And I choose "Preview question" in the open action menu
    And I wait until ".mod_kahoodle-overlay [data-stage='preview']" "css_element" exists
    Then I should see "What is the capital of France?"
    When I click on "[data-action='close']" "css_element"
    Then ".mod_kahoodle-overlay" "css_element" should not exist
    # Still on the question management page.
    And I should see "What is the capital of France?" in the "Questions" "table"

  Scenario: Preview starting from a specific question
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I navigate to "Questions" in current page administration
    # Preview from the second question.
    And I click on "Actions" "link" in the "What is 2 + 2?" "table_row"
    And I choose "Preview question" in the open action menu
    And I wait until ".mod_kahoodle-overlay [data-stage='preview']" "css_element" exists
    # Should start at question 2.
    Then I should see "What is 2 + 2?" in the ".mod_kahoodle-overlay [data-stage='preview']" "css_element"
    And I should see "2 of 3" in the ".mod_kahoodle-overlay" "css_element"
    # Advance through question 2 stages to reach question 3.
    When I click on "[data-action='next']" "css_element"
    And I wait until ".mod_kahoodle-overlay [data-stage='question']" "css_element" exists
    And I click on "[data-action='next']" "css_element"
    And I wait until ".mod_kahoodle-overlay [data-stage='results']" "css_element" exists
    And I click on "[data-action='next']" "css_element"
    And I wait until ".mod_kahoodle-overlay [data-stage='preview']" "css_element" exists
    Then I should see "What color is the sky?" in the ".mod_kahoodle-overlay [data-stage='preview']" "css_element"
    And I should see "3 of 3" in the ".mod_kahoodle-overlay" "css_element"
