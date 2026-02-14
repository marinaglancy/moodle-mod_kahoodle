@mod @mod_kahoodle
Feature: Kahoodle lobby and join form
  In order to participate in a Kahoodle activity
  As a student
  I need to be able to join a game through the lobby with any identity mode

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

  @javascript
  Scenario: Student joins a game with real name identity mode
    Given the following "activities" exist:
      | activity | name          | course | idnumber  | identitymode |
      | kahoodle | Test Kahoodle | C1     | kahoodle1 | 0            |
    And the kahoodle "Test Kahoodle" round stage is "lobby"
    # Student sees the join form.
    When I log in as "student1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    Then I should see "The game is on!" in the ".mod-kahoodle-landing" "css_element"
    And I should see "Join as" in the ".mod-kahoodle-landing" "css_element"
    And I should see "Sam Student" in the ".mod-kahoodle-landing" "css_element"
    # Student joins the game and enters the lobby.
    When I press "Join"
    Then I should see "You are joining as" in the ".mod_kahoodle-participant-container" "css_element"
    And I should see "Sam Student" in the ".mod_kahoodle-participant-container" "css_element"
    # Make sure all php polling requests are finished before the end of the test
    When the kahoodle "Test Kahoodle" round stage is "archived"
    And I wait until "The activity has finished." "text" exists

  @javascript
  Scenario: Student joins a game with optional alias using real name
    Given the following "activities" exist:
      | activity | name          | course | idnumber  | identitymode |
      | kahoodle | Test Kahoodle | C1     | kahoodle1 | 1            |
    And the kahoodle "Test Kahoodle" round stage is "lobby"
    # Student sees both identity options.
    When I log in as "student1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    Then I should see "The game is on!" in the ".mod-kahoodle-landing" "css_element"
    And I should see "Join as" in the ".mod-kahoodle-landing" "css_element"
    And I should see "Sam Student" in the ".mod-kahoodle-landing" "css_element"
    # Student joins with real name (default selection).
    When I press "Join"
    Then I should see "You are joining as" in the ".mod_kahoodle-participant-container" "css_element"
    And I should see "Sam Student" in the ".mod_kahoodle-participant-container" "css_element"
    # Make sure all php polling requests are finished before the end of the test
    When the kahoodle "Test Kahoodle" round stage is "archived"
    And I wait until "The activity has finished." "text" exists

  @javascript
  Scenario: Student joins a game with optional alias using a nickname
    Given the following "activities" exist:
      | activity | name          | course | idnumber  | identitymode |
      | kahoodle | Test Kahoodle | C1     | kahoodle1 | 1            |
    And the kahoodle "Test Kahoodle" round stage is "lobby"
    # Student sees both identity options.
    When I log in as "student1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    Then I should see "The game is on!" in the ".mod-kahoodle-landing" "css_element"
    # Student selects the alias option and enters a nickname.
    When I click on "input[name='identitychoice'][value='alias']" "css_element" in the ".mod-kahoodle-landing" "css_element"
    And I press "Join"
    And I should see "Required" in the ".mod-kahoodle-landing" "css_element"
    And I set the field "displayname" to "CoolPlayer"
    And I press "Join"
    Then I should see "You are joining as" in the ".mod_kahoodle-participant-container" "css_element"
    And I should see "CoolPlayer" in the ".mod_kahoodle-participant-container" "css_element"
    And I should not see "Sam Student" in the ".mod_kahoodle-participant-container" "css_element"
    # Make sure all php polling requests are finished before the end of the test
    When the kahoodle "Test Kahoodle" round stage is "archived"
    And I wait until "The activity has finished." "text" exists

  @javascript
  Scenario: Student joins a game with required alias identity mode
    Given the following "activities" exist:
      | activity | name          | course | idnumber  | identitymode |
      | kahoodle | Test Kahoodle | C1     | kahoodle1 | 2            |
    And the kahoodle "Test Kahoodle" round stage is "lobby"
    # Student sees the nickname field.
    When I log in as "student1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    Then I should see "The game is on!" in the ".mod-kahoodle-landing" "css_element"
    And I should see "Join as" in the ".mod-kahoodle-landing" "css_element"
    # Student enters a nickname and joins.
    When I set the field "displayname" to "SpeedyQuizzer"
    And I press "Join"
    Then I should see "You are joining as" in the ".mod_kahoodle-participant-container" "css_element"
    And I should see "SpeedyQuizzer" in the ".mod_kahoodle-participant-container" "css_element"
    And I should not see "Sam Student" in the ".mod_kahoodle-participant-container" "css_element"
    # Make sure all php polling requests are finished before the end of the test
    When the kahoodle "Test Kahoodle" round stage is "archived"
    And I wait until "The activity has finished." "text" exists

  @javascript
  Scenario: Student joins a game with fully anonymous identity mode
    Given the following "activities" exist:
      | activity | name          | course | idnumber  | identitymode |
      | kahoodle | Test Kahoodle | C1     | kahoodle1 | 3            |
    And the kahoodle "Test Kahoodle" round stage is "lobby"
    # Student sees the nickname field.
    When I log in as "student1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    Then I should see "The game is on!" in the ".mod-kahoodle-landing" "css_element"
    And I should see "Join as" in the ".mod-kahoodle-landing" "css_element"
    # Student enters a nickname and joins.
    When I set the field "displayname" to "MysteryPlayer"
    And I press "Join"
    Then I should see "You are joining as" in the ".mod_kahoodle-participant-container" "css_element"
    And I should see "MysteryPlayer" in the ".mod_kahoodle-participant-container" "css_element"
    And I should not see "Sam Student" in the ".mod_kahoodle-participant-container" "css_element"
    # Make sure all php polling requests are finished before the end of the test
    When the kahoodle "Test Kahoodle" round stage is "archived"
    And I wait until "The activity has finished." "text" exists

  @javascript
  Scenario: Student in required alias mode without avatar pool does not see edit avatar button
    Given the following "activities" exist:
      | activity | name          | course | idnumber  | identitymode |
      | kahoodle | Test Kahoodle | C1     | kahoodle1 | 2            |
    And the following "mod_kahoodle > questions" exist:
      | kahoodle      | questiontext    | questionconfig |
      | Test Kahoodle | Sample question | *A\nB\nC       |
    And the kahoodle "Test Kahoodle" round stage is "lobby"
    When I log in as "student1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I set the field "displayname" to "TestPlayer"
    And I press "Join"
    Then I should see "You are joining as" in the ".mod_kahoodle-participant-container" "css_element"
    And I should see "TestPlayer" in the ".mod_kahoodle-participant-container" "css_element"
    # No avatar pool images uploaded, so edit avatar button should not appear.
    And "[data-action='editavatar']" "css_element" should not exist
    # Make sure all php polling requests are finished before the end of the test
    When the kahoodle "Test Kahoodle" round stage is "archived"
    And I wait until "The activity has finished." "text" exists

  @javascript
  Scenario: Student in required alias mode with avatar pool can select avatar
    Given the following "activities" exist:
      | activity | name          | course | idnumber  | identitymode |
      | kahoodle | Test Kahoodle | C1     | kahoodle1 | 2            |
    And the following "mod_kahoodle > questions" exist:
      | kahoodle      | questiontext    | questionconfig |
      | Test Kahoodle | Sample question | *A\nB\nC       |
    And "21" images are uploaded to the kahoodle avatar pool
    And the kahoodle "Test Kahoodle" round stage is "lobby"
    When I log in as "student1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I set the field "displayname" to "TestPlayer"
    And I press "Join"
    Then I should see "You are joining as" in the ".mod_kahoodle-participant-container" "css_element"
    And "[data-action='editavatar']" "css_element" should exist
    # Open avatar picker and select the first candidate.
    When I click on "[data-action='editavatar']" "css_element"
    Then "[data-region='avatar-picker']" "css_element" should be visible
    When I click on "[data-action='selectavatar']" "css_element"
    # After selecting an avatar, the edit button is removed.
    Then "[data-action='editavatar']" "css_element" should not exist
    # Make sure all php polling requests are finished before the end of the test
    When the kahoodle "Test Kahoodle" round stage is "archived"
    And I wait until "The activity has finished." "text" exists

  @javascript
  Scenario: Student can see more avatar candidates with show more button
    Given the following "activities" exist:
      | activity | name          | course | idnumber  | identitymode |
      | kahoodle | Test Kahoodle | C1     | kahoodle1 | 2            |
    And the following "mod_kahoodle > questions" exist:
      | kahoodle      | questiontext    | questionconfig |
      | Test Kahoodle | Sample question | *A\nB\nC       |
    And "21" images are uploaded to the kahoodle avatar pool
    And the kahoodle "Test Kahoodle" round stage is "lobby"
    When I log in as "student1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I set the field "displayname" to "TestPlayer"
    And I press "Join"
    Then "[data-action='editavatar']" "css_element" should exist
    When I click on "[data-action='editavatar']" "css_element"
    # Wait for the initial batch of 8 candidates to load via AJAX.
    And I wait until ".mod_kahoodle-avatar-picker-item:nth-child(8)" "css_element" exists
    # Initially only 8 avatar candidates are shown.
    Then ".mod_kahoodle-avatar-picker-item:nth-child(9)" "css_element" should not exist
    # Click show more to load additional candidates.
    When I click on "[data-action='showmore']" "css_element"
    And I wait until ".mod_kahoodle-avatar-picker-item:nth-child(9)" "css_element" exists
    # Select the 9th candidate.
    When I click on ".mod_kahoodle-avatar-picker-item:nth-child(9)" "css_element"
    # After selecting an avatar, the edit button is removed.
    Then "[data-action='editavatar']" "css_element" should not exist
    # Make sure all php polling requests are finished before the end of the test
    When the kahoodle "Test Kahoodle" round stage is "archived"
    And I wait until "The activity has finished." "text" exists

  @javascript
  Scenario: Facilitator with participate capability can join and leave the game
    Given the following "activities" exist:
      | activity | name          | course | idnumber  | identitymode | questionpreviewduration | questionduration | questionresultsduration |
      | kahoodle | Test Kahoodle | C1     | kahoodle1 | 2            | 120                     | 120              | 120                     |
    And the following "mod_kahoodle > questions" exist:
      | kahoodle      | questiontext                   | questionconfig         |
      | Test Kahoodle | What is the capital of France? | London\n*Paris\nBerlin |
    And the following "permission overrides" exist:
      | capability               | permission | role           | contextlevel    | reference |
      | mod/kahoodle:participate | Allow      | editingteacher | Activity module | kahoodle1 |
    And the kahoodle "Test Kahoodle" round stage is "lobby"
    # Teacher visits the page and sees the facilitator overlay.
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I wait until ".mod_kahoodle-overlay" "css_element" exists
    And I click on "[data-action='close']" "css_element"
    # Landing page shows both facilitator controls and join form.
    Then I should see "Facilitator controls"
    And I should see "Join as participant"
    # Teacher joins as a participant.
    When I set the field "displayname" to "TeacherPlayer"
    And I press "Join as participant"
    Then I should see "You are joining as" in the ".mod_kahoodle-participant-container" "css_element"
    And I should see "TeacherPlayer" in the ".mod_kahoodle-participant-container" "css_element"
    # Advance to question stage (skipping preview).
    When the kahoodle "Test Kahoodle" round stage is "question-1"
    And I wait until ".mod_kahoodle-participant-question-content" "css_element" exists
    # Answer the question.
    And I click on ".mod_kahoodle-option1" "css_element"
    Then I should see "Waiting for results..." in the ".mod_kahoodle-participant-container" "css_element"
    # Close the participant overlay to return to the landing page.
    When I click on "[data-action='close']" "css_element"
    Then I should see "Participant controls"
    And I should see "Join as facilitator"
    # Leave the round to return to facilitator role.
    When I follow "Join as facilitator"
    And I wait until ".mod_kahoodle-overlay" "css_element" exists
    And I click on "[data-action='close']" "css_element"
    # Landing page shows both sections again with the join form.
    Then I should see "Facilitator controls"
    And I should see "Join as participant"
    # Make sure all php polling requests are finished before the end of the test
    When the kahoodle "Test Kahoodle" round stage is "archived"
    And I wait until "The activity has finished." "text" exists
