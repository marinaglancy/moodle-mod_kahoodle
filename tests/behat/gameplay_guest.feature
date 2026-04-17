@mod @mod_kahoodle
Feature: Guest users can participate in a Kahoodle activity
  In order to run Kahoodle sessions for public events without Moodle accounts
  As a facilitator
  Guests need to join a fully anonymous round, answer questions, and appear in results

  Background:
    # Multiple things need to be set up to allow guest users to join a Kahoodle activity:
    # - Site allows guest access (we also recommend enabling the "Auto login guests" setting)
    # - In the Real time events plugin settings, enable guest access
    # - Enable guest access for the course containing the Kahoodle activity
    # - Set the activity's identity mode to Fully anonymous
    # - Allow the mod/kahoodle:participateguest capability for the Guest role (site-wide or for the selected course/activity)
    Given the following config values are set as admin:
      | autologinguests | 1 |               |
      | allowguests     | 1 | tool_realtime |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Terry     | Teacher  | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity | name          | course | idnumber  | identitymode |
      | kahoodle | Test Kahoodle | C1     | kahoodle1 | 3            |
    And the following "permission overrides" exist:
      | capability                    | permission | role  | contextlevel    | reference |
      | mod/kahoodle:participateguest | Allow      | guest | Activity module | kahoodle1 |
    # Enable guest access on the course via the UI (no direct data generator exists).
    And I am on the "Course 1" "enrolment methods" page logged in as "teacher1"
    And I click on "Enable" "link" in the "Guest access" "table_row"
    And I log out

  @javascript
  Scenario: Guest joins, answers a question, and appears in the teacher's results
    Given the following config values are set as admin:
      | requesttimeout   | 2 | realtimeplugin_phppoll |
    And the following "mod_kahoodle > questions" exist:
      | kahoodle      | questiontext       | questionconfig         |
      | Test Kahoodle | Capital of France? | London\n*Paris\nBerlin |
    Given the kahoodle "Test Kahoodle" round stage is "lobby"
    # With autologinguests=1, visiting a course page logs the user in as guest automatically.
    When I am on the "Test Kahoodle" "kahoodle activity" page
    Then I should see "The game is on!" in the ".mod-kahoodle-landing" "css_element"
    And I should see "Join as" in the ".mod-kahoodle-landing" "css_element"
    When I set the field "displayname" to "GuestPlayer"
    And I press "Join"
    Then I should see "You are joining as" in the ".mod_kahoodle-participant-container" "css_element"
    And I should see "GuestPlayer" in the ".mod_kahoodle-participant-container" "css_element"
    # Advance to question preview - guest screen updates via realtime.
    When the kahoodle "Test Kahoodle" round stage is "preview-1"
    And I wait until ".mod_kahoodle-participant-preview-content" "css_element" exists
    Then I should see "Question 1" in the ".mod_kahoodle-participant-preview-content" "css_element"
    And I should see "Get ready!" in the ".mod_kahoodle-participant-preview-content" "css_element"
    # Advance to question - guest submits a correct answer.
    When the kahoodle "Test Kahoodle" round stage is "question-1"
    And I wait until ".mod_kahoodle-participant-question-content" "css_element" exists
    And I click on ".mod_kahoodle-option2" "css_element"
    Then I should see "Waiting for results..." in the ".mod_kahoodle-participant-container" "css_element"
    # Advance to results - guest sees correctness feedback and earned points.
    When the kahoodle "Test Kahoodle" round stage is "results-1"
    And I wait until ".mod_kahoodle-participant-result-content" "css_element" exists
    Then I should see "Correct!" in the ".mod_kahoodle-participant-result-content" "css_element"
    And ".mod_kahoodle-participant-result-score" "css_element" should exist
    # Game ends.
    When the kahoodle "Test Kahoodle" round stage is "archived"
    And I wait until "The activity has finished." "text" exists
    And I log out
    # Teacher logs in, views results and sees the guest participant.
    When I log in as "teacher1"
    And I am on the "Test Kahoodle" "kahoodle activity" page
    And I follow "View results"
    Then "//dd[@data-field='participants'][contains(.,'1')]" "xpath_element" should exist in the "Round 1" "mod_kahoodle > round result"
    When I follow "All rounds: Participants"
    Then I should see "GuestPlayer"
