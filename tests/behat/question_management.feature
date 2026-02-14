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
    When I click on "Actions" "link" in the "What is 2 + 2?" "table_row"
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

  @javascript
  Scenario: Deleting a question
    Given the following "mod_kahoodle > questions" exist:
      | kahoodle  | questiontext               | questionconfig       |
      | kahoodle1 | What is the capital of UK? | *London\nParis\nRome |
      | kahoodle1 | What is 5 + 5?             | 8\n9\n*10            |
      | kahoodle1 | What color is the sky?     | Green\n*Blue\nRed    |
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I navigate to "Questions" in current page administration
    Then I should see "What is the capital of UK?"
    And I should see "What is 5 + 5?"
    And I should see "What color is the sky?"
    When I click on "Actions" "link" in the "What is 5 + 5?" "table_row"
    And I choose "Delete" in the open action menu
    And I click on "Delete" "button" in the "Delete question" "dialogue"
    Then I should see "What is the capital of UK?"
    And I should not see "What is 5 + 5?"
    And I should see "What color is the sky?"

  @javascript
  Scenario: Changing question sort order
    Given the following "mod_kahoodle > questions" exist:
      | kahoodle  | questiontext               | questionconfig |
      | kahoodle1 | What is the capital of UK? | *A\nB\nC       |
      | kahoodle1 | What is 2 + 2?             | *A\nB\nC       |
      | kahoodle1 | What color is the sky?     | *A\nB\nC       |
      | kahoodle1 | How many days in a week?   | *A\nB\nC       |
      | kahoodle1 | What is the largest ocean? | *A\nB\nC       |
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I navigate to "Questions" in current page administration
    And I press "Move Question 2"
    And I click on "After \"Question 5\"" "link" in the "Move Question 2" "dialogue"
    Then "What is the capital of UK?" "table_row" should appear before "What color is the sky?" "table_row"
    And "What color is the sky?" "table_row" should appear before "How many days in a week?" "table_row"
    And "How many days in a week?" "table_row" should appear before "What is the largest ocean?" "table_row"
    And "What is the largest ocean?" "table_row" should appear before "What is 2 + 2?" "table_row"

  @javascript
  Scenario: Duplicating a question in the current round
    Given the following "mod_kahoodle > questions" exist:
      | kahoodle  | questiontext               | questionconfig       |
      | kahoodle1 | What is the capital of UK? | *London\nParis\nRome |
      | kahoodle1 | What is 5 + 5?             | 8\n9\n*10            |
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I navigate to "Questions" in current page administration
    And I click on "Actions" "link" in the "What is the capital of UK?" "table_row"
    And I choose "Duplicate question" in the open action menu
    Then the following should exist in the "Questions" table:
      | Order | Question text              |
      | 1     | What is the capital of UK? |
      | 2     | What is the capital of UK? |
      | 3     | What is 5 + 5?             |

  @javascript
  Scenario: Editing a question in a started round without responses
    Given the following "mod_kahoodle > questions" exist:
      | kahoodle  | questiontext                   | questionconfig       |
      | kahoodle1 | What is the capital of France? | London\n*Paris\nRome |
    And the kahoodle "Test Kahoodle" round stage is "lobby"
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I wait until ".mod_kahoodle-overlay" "css_element" exists
    And I click on "[data-action='close']" "css_element"
    And I navigate to "Questions" in current page administration
    And I click on "Actions" "link" in the "What is the capital of France?" "table_row"
    And I choose "Edit question" in the open action menu
    # No warning about responses since none exist yet.
    Then I should not see "This question already has responses from participants"
    When I set the field "Answer options" to multiline:
      """
      London
      *Paris
      Rome
      Madrid
      """
    And I click on "Save changes" "button" in the "Edit question" "dialogue"
    Then I should see "What is the capital of France?"
    # Verify the change was saved by reopening the edit form.
    When I click on "Actions" "link" in the "What is the capital of France?" "table_row"
    And I choose "Edit question" in the open action menu
    Then I should see "Madrid" in the "Edit question" "dialogue"

  @javascript
  Scenario: Editing a question with existing responses shows warnings and restrictions
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Sam       | Student  | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "mod_kahoodle > questions" exist:
      | kahoodle  | questiontext                   | questionconfig       |
      | kahoodle1 | What is the capital of France? | London\n*Paris\nRome |
    And the kahoodle "Test Kahoodle" round stage is "lobby"
    And the following "mod_kahoodle > participants" exist:
      | kahoodle      | user     | displayname |
      | Test Kahoodle | student1 | Sam         |
    And the following "mod_kahoodle > responses" exist:
      | kahoodle      | user     | question | response | iscorrect | points |
      | Test Kahoodle | student1 | 1        | 2        | 1         | 900    |
    And the kahoodle "Test Kahoodle" round stage is "archived"
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I navigate to "Questions" in current page administration
    And I click on "Actions" "link" in the "What is the capital of France?" "table_row"
    And I choose "Edit question" in the open action menu
    # Warning about existing responses is shown.
    Then I should see "This question already has responses from participants"
    # Attempt 1: Try to add a new answer option.
    When I set the field "Answer options" to multiline:
      """
      London
      *Paris
      Rome
      Madrid
      """
    And I click on "Save changes" "button" in the "Edit question" "dialogue"
    Then I should see "The number of answer options cannot be changed"
    # Attempt 2: Try to change the correct answer position.
    When I set the field "Answer options" to multiline:
      """
      *London
      Paris
      Rome
      """
    And I click on "Save changes" "button" in the "Edit question" "dialogue"
    Then I should see "The position of the correct answer cannot be changed"
    # Attempt 3: Change only the option text (allowed change).
    When I set the field "Answer options" to multiline:
      """
      Berlin
      *Paris
      Rome
      """
    And I click on "Save changes" "button" in the "Edit question" "dialogue"
    Then I should see "What is the capital of France?"
    # Verify the change was saved by reopening the edit form.
    When I click on "Actions" "link" in the "What is the capital of France?" "table_row"
    And I choose "Edit question" in the open action menu
    Then I should see "Berlin" in the "Edit question" "dialogue"
    And I should not see "London" in the "Edit question" "dialogue"

  @javascript
  Scenario: Duplicating a question from an archived round into the preparation round
    Given the following "mod_kahoodle > questions" exist:
      | kahoodle  | questiontext               | questionconfig       |
      | kahoodle1 | What is the capital of UK? | *London\nParis\nRome |
      | kahoodle1 | What is 5 + 5?             | 8\n9\n*10            |
    # Archive the round so it is no longer editable.
    And the kahoodle "Test Kahoodle" round stage is "lobby"
    And the kahoodle "Test Kahoodle" round stage is "archived"
    # Create a new round in preparation stage.
    And I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I follow "Prepare new round"
    # Go to the archived round's question management page.
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I navigate to "Results" in current page administration
    And I click on "Manage questions" "link" in the "Round 1" "mod_kahoodle > round result"
    And I click on "Actions" "link" in the "What is 5 + 5?" "table_row"
    And I choose "Duplicate question" in the open action menu
    # Confirmation dialog appears.
    Then I should see "duplicated into the last round" in the "Duplicate question" "dialogue"
    When I click on "Duplicate question" "button" in the "Duplicate question" "dialogue"
    # Redirected to the preparation round's question management page.
    Then "Add question" "button" should exist
    And the following should exist in the "Questions" table:
      | Order | Question text              |
      | 1     | What is the capital of UK? |
      | 2     | What is 5 + 5?             |
      | 3     | What is 5 + 5?             |
