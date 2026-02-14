@mod @mod_kahoodle @javascript
Feature: Events logging in Kahoodle
  As a teacher
  I can verify that Kahoodle activities log events correctly in the course logs

  Background:
    Given the following config values are set as admin:
      | requesttimeout | 2 | realtimeplugin_phppoll |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Terry     | Teacher  | teacher1@example.com |
      | student1 | Sam       | Student  | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |

  Scenario: Facilitator question management events appear in logs
    Given the following "activities" exist:
      | activity | name          | course | idnumber  |
      | kahoodle | Test Kahoodle | C1     | kahoodle1 |
    And the following "mod_kahoodle > questions" exist:
      | kahoodle      | questiontext    | questionconfig |
      | Test Kahoodle | Sample question | *A\nB\nC       |
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I navigate to "Questions" in current page administration
    # Create a question.
    And I click on "Add question" "button"
    And I click on "Multiple choice" "link"
    And I set the field "Question text" in the "Add question: Multiple choice" "dialogue" to "What is 1+1?"
    And I set the field "Answer options" to multiline:
      """
      1
      *2
      3
      """
    And I click on "Save changes" "button" in the "Add question: Multiple choice" "dialogue"
    # Edit the question.
    And I click on "Actions" "link" in the "What is 1+1?" "table_row"
    And I choose "Edit question" in the open action menu
    And I set the field "Question text" in the "Edit question" "dialogue" to "What is 2+2?"
    And I click on "Save changes" "button" in the "Edit question" "dialogue"
    # Delete a question.
    And I click on "Actions" "link" in the "Sample question" "table_row"
    And I choose "Delete" in the open action menu
    And I click on "Delete" "button" in the "Delete question" "dialogue"
    # Check logs.
    And I am on "Course 1" course homepage
    And I navigate to "Reports" in current page administration
    And I click on "Logs" "link"
    And I press "Get these logs"
    Then I should see "Question created"
    And I should see "Question updated"
    And I should see "Question removed"
    And I should see "Terry Teacher"

  Scenario: Student response event shows student name as affected user
    Given the following "activities" exist:
      | activity | name          | course | idnumber  | identitymode |
      | kahoodle | Test Kahoodle | C1     | kahoodle1 | 0            |
    And the following "mod_kahoodle > questions" exist:
      | kahoodle      | questiontext    | questionconfig |
      | Test Kahoodle | Sample question | *A\nB\nC       |
    And the kahoodle "Test Kahoodle" round stage is "lobby"
    When I log in as "student1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I press "Join"
    And I should see "You are joining as" in the ".mod_kahoodle-participant-container" "css_element"
    # Advance to question stage and answer.
    And the kahoodle "Test Kahoodle" round stage is "question-1"
    And I wait until ".mod_kahoodle-participant-question-content" "css_element" exists
    And I click on ".mod_kahoodle-option1" "css_element"
    And I should see "Waiting for results..." in the ".mod_kahoodle-participant-container" "css_element"
    # Archive the round to stop polling.
    When the kahoodle "Test Kahoodle" round stage is "archived"
    And I wait until "The activity has finished." "text" exists
    # Check logs as teacher.
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to "Reports" in current page administration
    And I click on "Logs" "link"
    And I press "Get these logs"
    Then I should see "Response submitted"
    And I should see "Sam Student"

  Scenario: Anonymous kahoodle does not log response events before results stage
    Given the following "activities" exist:
      | activity | name          | course | idnumber  | identitymode |
      | kahoodle | Test Kahoodle | C1     | kahoodle1 | 3            |
    And the following "mod_kahoodle > questions" exist:
      | kahoodle      | questiontext    | questionconfig |
      | Test Kahoodle | Sample question | *A\nB\nC       |
    And the kahoodle "Test Kahoodle" round stage is "lobby"
    And the following "mod_kahoodle > participants" exist:
      | kahoodle      | user     | displayname | totalscore |
      | Test Kahoodle | student1 | AnonPlayer  | 0          |
    # Advance to question stage and create a response via generator (no event triggered).
    And the kahoodle "Test Kahoodle" round stage is "question-1"
    And the following "mod_kahoodle > responses" exist:
      | kahoodle      | user     | question | response | iscorrect | points |
      | Test Kahoodle | student1 | 1        | 1        | 1         | 900    |
    # Do not advance to results - deferred events should not be triggered yet.
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to "Reports" in current page administration
    And I click on "Logs" "link"
    And I press "Get these logs"
    Then I should not see "Response submitted"
    And I should not see "Participant joined"

  Scenario: Anonymous response events appear after results stage without user name
    Given the following "activities" exist:
      | activity | name          | course | idnumber  | identitymode |
      | kahoodle | Test Kahoodle | C1     | kahoodle1 | 3            |
    And the following "mod_kahoodle > questions" exist:
      | kahoodle      | questiontext    | questionconfig |
      | Test Kahoodle | Sample question | *A\nB\nC       |
    And the kahoodle "Test Kahoodle" round stage is "lobby"
    And the following "mod_kahoodle > participants" exist:
      | kahoodle      | user     | displayname | totalscore |
      | Test Kahoodle | student1 | AnonPlayer  | 0          |
    And the kahoodle "Test Kahoodle" round stage is "question-1"
    And the following "mod_kahoodle > responses" exist:
      | kahoodle      | user     | question | response | iscorrect | points |
      | Test Kahoodle | student1 | 1        | 1        | 1         | 900    |
    # Advance through results stage - triggers deferred anonymous response events.
    And the kahoodle "Test Kahoodle" round stage is "archived"
    # Flush logstore buffer so events from the behat process are visible to the web server.
    And the logstore buffer is flushed
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to "Reports" in current page administration
    And I click on "Logs" "link"
    And I press "Get these logs"
    Then I should see "Response submitted"
    # Student's real name should not appear in the response event since the participant is fully anonymous.
    And I should not see "Sam Student" in the "Response submitted" "table_row"
    # No participant joined event in anonymous mode.
    And I should not see "Participant joined"

  Scenario: Round created and updated events appear in logs
    Given the following "activities" exist:
      | activity | name          | course | idnumber  |
      | kahoodle | Test Kahoodle | C1     | kahoodle1 |
    And the following "mod_kahoodle > questions" exist:
      | kahoodle      | questiontext    | questionconfig |
      | Test Kahoodle | Sample question | *A\nB\nC       |
    # Archive the first round so the teacher can prepare a new one.
    And the kahoodle "Test Kahoodle" round stage is "lobby"
    And the kahoodle "Test Kahoodle" round stage is "archived"
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I follow "Prepare new round"
    # Flush logstore buffer so round_updated events from stage changes are visible.
    And the logstore buffer is flushed
    # Check logs for round events.
    And I am on "Course 1" course homepage
    And I navigate to "Reports" in current page administration
    And I click on "Logs" "link"
    And I press "Get these logs"
    Then I should see "Round created"
    And I should see "Round updated"
    And I should see "Terry Teacher"

  Scenario: Participant join and leave events appear in logs
    Given the following "activities" exist:
      | activity | name          | course | idnumber  | identitymode | questionpreviewduration | questionduration | questionresultsduration |
      | kahoodle | Test Kahoodle | C1     | kahoodle1 | 2            | 120                     | 120              | 120                     |
    And the following "mod_kahoodle > questions" exist:
      | kahoodle      | questiontext    | questionconfig |
      | Test Kahoodle | Sample question | *A\nB\nC       |
    And the following "permission overrides" exist:
      | capability               | permission | role           | contextlevel    | reference |
      | mod/kahoodle:participate | Allow      | editingteacher | Activity module | kahoodle1 |
    And the kahoodle "Test Kahoodle" round stage is "lobby"
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I wait until ".mod_kahoodle-overlay" "css_element" exists
    And I click on "[data-action='close']" "css_element"
    # Teacher joins as participant.
    And I set the field "displayname" to "TeacherPlayer"
    And I press "Join as participant"
    And I should see "You are joining as" in the ".mod_kahoodle-participant-container" "css_element"
    # Leave the round to return to facilitator role.
    And I click on "[data-action='close']" "css_element"
    And I follow "Join as facilitator"
    And I wait until ".mod_kahoodle-overlay" "css_element" exists
    And I click on "[data-action='close']" "css_element"
    # Archive to stop polling.
    And I click on "End round for everyone" "button"
    And I click on "Yes" "button" in the "Finish activity" "dialogue"
    And I wait until "The activity has finished." "text" exists
    # Check logs for participant events.
    And I am on "Course 1" course homepage
    And I navigate to "Reports" in current page administration
    And I click on "Logs" "link"
    And I press "Get these logs"
    Then I should see "Participant joined"
    And I should see "Participant left"
    And I should see "Terry Teacher"
