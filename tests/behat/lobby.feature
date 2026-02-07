@mod @mod_kahoodle
Feature: Kahoodle lobby and join form
  In order to participate in a Kahoodle activity
  As a student
  I need to be able to join a game through the lobby

  Background:
    Given the following "users" exist:
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
    And I set the field "displayname" to "CoolPlayer"
    And I press "Join"
    Then I should see "You are joining as" in the ".mod_kahoodle-participant-container" "css_element"
    And I should see "CoolPlayer" in the ".mod_kahoodle-participant-container" "css_element"
    And I should not see "Sam Student" in the ".mod_kahoodle-participant-container" "css_element"

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
