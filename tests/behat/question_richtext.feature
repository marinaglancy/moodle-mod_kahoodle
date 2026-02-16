@mod @mod_kahoodle @javascript
Feature: Managing rich text questions in Kahoodle
  In order to create engaging quizzes with formatted text and images
  As a teacher
  I need to be able to add, edit, and duplicate rich text questions in Kahoodle

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
      | activity | name          | course | idnumber  | questionformat | questionpreviewduration | questionduration | questionresultsduration |
      | kahoodle | Test Kahoodle | C1     | kahoodle1 | 1              | 120                     | 120              | 120                     |

  Scenario: Adding a rich text question without separate image field
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I navigate to "Questions" in current page administration
    And I click on "Add question" "button"
    And I click on "Multiple choice" "link"
    # Rich text mode uses an editor; no separate "Question image" filemanager.
    Then I should not see "Question image" in the "Add question: Multiple choice" "dialogue"
    When I set the field "Question text" in the "Add question: Multiple choice" "dialogue" to "<h3>What is the <b>capital</b> of France?</h3>"
    And I set the field "Answer options" to multiline:
      """
      London
      *Paris
      Berlin
      """
    And I click on "Save changes" "button" in the "Add question: Multiple choice" "dialogue"
    Then I should see "What is the capital of France?"

  Scenario: Rich text question without h3 tag shows validation error
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I navigate to "Questions" in current page administration
    And I click on "Add question" "button"
    And I click on "Multiple choice" "link"
    # Question text without an h3 tag should be rejected.
    When I set the field "Question text" in the "Add question: Multiple choice" "dialogue" to "<p>No heading here</p>"
    And I set the field "Answer options" to multiline:
      """
      Option A
      *Option B
      """
    And I click on "Save changes" "button" in the "Add question: Multiple choice" "dialogue"
    Then I should see "Question text must contain a heading"
    # Fix the question by adding an h3 tag.
    When I set the field "Question text" in the "Add question: Multiple choice" "dialogue" to "<h3>Now with heading</h3>"
    And I click on "Save changes" "button" in the "Add question: Multiple choice" "dialogue"
    Then I should see "Now with heading"

  Scenario: Editing a rich text question
    Given the following "mod_kahoodle > questions" exist:
      | kahoodle      | questiontext                                  | questionconfig         |
      | Test Kahoodle | <h3>What is the capital of France?</h3> | London\n*Paris\nBerlin |
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I navigate to "Questions" in current page administration
    Then I should see "What is the capital of France?"
    When I click on "Actions" "link" in the "What is the capital of France?" "table_row"
    And I choose "Edit question" in the open action menu
    And I set the field "Question text" in the "Edit question" "dialogue" to "<h3>What is the <i>largest</i> ocean?</h3>"
    And I set the field "Answer options" to multiline:
      """
      Atlantic
      *Pacific
      Indian
      """
    And I click on "Save changes" "button" in the "Edit question" "dialogue"
    Then I should see "What is the largest ocean?"
    And I should not see "What is the capital of France?"

  Scenario: Duplicating a rich text question
    Given the following "mod_kahoodle > questions" exist:
      | kahoodle      | questiontext                                  | questionconfig         |
      | Test Kahoodle | <h3>What is the capital of France?</h3> | London\n*Paris\nBerlin |
      | Test Kahoodle | <h3>What is 5 + 5?</h3>                       | 8\n9\n*10              |
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I navigate to "Questions" in current page administration
    And I click on "Actions" "link" in the "What is the capital of France?" "table_row"
    And I choose "Duplicate question" in the open action menu
    Then the following should exist in the "Questions" table:
      | Order | Question text                  |
      | 1     | What is the capital of France? |
      | 2     | What is the capital of France? |
      | 3     | What is 5 + 5?                 |
