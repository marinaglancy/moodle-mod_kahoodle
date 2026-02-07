@mod @mod_kahoodle
Feature: Basic operations with module Kahoodle
  In order to use Kahoodle in Moodle
  As a teacher and student
  I need to be able to modify and view Kahoodle

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
  Scenario: Viewing Kahoodle module and activities index page
    Given the site is running Moodle version 5.0.99 or lower
    And the following "activities" exist:
      | activity | name             | course | intro                   | section |
      | kahoodle | Test module name | C1     | Test module description | 1       |
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Activities" block
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I click on "Test module name" "link" in the "region-main" "region"
    Then I should see "Test module description"
    And I am on "Course 1" course homepage
    And I click on "Kahoodles" "link" in the "Activities" "block"
    And I should see "1" in the "Test module name" "table_row"

  @javascript
  Scenario: Viewing Kahoodle module in the Activities tab in the course
    Given the site is running Moodle version 5.1 or higher
    And the following "activities" exist:
      | activity | name             | course | intro                   | section |
      | kahoodle | Test module name | C1     | Test module description | 1       |
    When I log in as "teacher1"
    And I am on the "Course 1" "course > activities > kahoodle" page
    Then the following should exist in the "Table listing all Kahoodle activities" table:
      | Name             |
      | Test module name |

  @javascript
  Scenario: Creating and updating Kahoodle module
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Kahoodle" to section 1 using the activity chooser
    And I set the following fields to these values:
      | Name                               | Test module name        |
      | Description                        | Test module description |
      | Display description on course page | 1                       |
    And I press "Save and return to course"
    And I open "Test module name" actions menu
    And I click on "Edit settings" "link" in the "Test module name" activity
    And I set the field "Name" to "Test module new name"
    And I press "Save and return to course"
    And I should see "Test module new name"
    And I should not see "Test module name"
    And I should see "Test module description"

  @javascript
  Scenario: Creating Kahoodle with custom settings
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Kahoodle" to section 1 using the activity chooser
    And I set the following fields to these values:
      | Name                                | Custom Kahoodle      |
      | Description                         | Custom settings test |
      | Allow repeat participation          | 1                    |
      | lobbyduration[number]               | 2                    |
      | lobbyduration[timeunit]             | minutes              |
      | Question preview duration, seconds  | 10                   |
      | Question duration, seconds          | 45                   |
      | Question results duration, seconds  | 15                   |
      | Maximum points                      | 2000                 |
      | Minimum points                      | 750                  |
    And I press "Save and return to course"
    Then I should see "Custom Kahoodle"
    When I open "Custom Kahoodle" actions menu
    And I click on "Edit settings" "link" in the "Custom Kahoodle" activity
    Then the following fields match these values:
      | Allow repeat participation          | 1       |
      | lobbyduration[number]               | 2       |
      | lobbyduration[timeunit]             | minutes |
      | Question preview duration, seconds  | 10      |
      | Question duration, seconds          | 45      |
      | Question results duration, seconds  | 15      |
      | Maximum points                      | 2000    |
      | Minimum points                      | 750     |

  @javascript
  Scenario: Verifying default Kahoodle settings
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Kahoodle" to section 1 using the activity chooser
    And I set the following fields to these values:
      | Name        | Default Settings Test |
      | Description | Testing defaults      |
    And I press "Save and display"
    And I navigate to "Settings" in current page administration
    Then the following fields match these values:
      | Allow repeat participation          | 0       |
      | lobbyduration[number]               | 5       |
      | lobbyduration[timeunit]             | minutes |
      | Question preview duration, seconds  | 5       |
      | Question duration, seconds          | 15      |
      | Question results duration, seconds  | 10      |
      | Maximum points                      | 1000    |
      | Minimum points                      | 500     |

  @javascript
  Scenario: Deleting Kahoodle module
    Given the following "activities" exist:
      | activity | name             | course | intro                   | section |
      | kahoodle | Test module name | C1     | Test module description | 1       |
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I open "Test module name" actions menu
    And I click on "Delete" "link" in the "Test module name" activity
    And I click on "Delete" "button" in the "Delete activity?" "dialogue"
    Then I should not see "Test module name" in the "region-main" "region"

  @javascript
  Scenario: Student viewing Kahoodle module
    Given the following "activities" exist:
      | activity | name             | course | intro                   | section |
      | kahoodle | Test module name | C1     | Test module description | 1       |
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I click on "Test module name" "link" in the "region-main" "region"
    Then I should see "Test module description"
    And I should see "Test module name"

  @javascript
  Scenario: Duplicating a Kahoodle activity preserves questions
    Given the following "activities" exist:
      | activity | name          | course | idnumber  | section |
      | kahoodle | Test Kahoodle | C1     | kahoodle1 | 1       |
    And the following "mod_kahoodle > questions" exist:
      | kahoodle  | questiontext                  | questionconfig        |
      | kahoodle1 | What is the capital of France? | Berlin\n*Paris\nRome  |
      | kahoodle1 | What is 2 + 2?                | 3\n*4\n5              |
      | kahoodle1 | Which planet is largest?      | Mars\nVenus\n*Jupiter |
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I duplicate "Test Kahoodle" activity
    And I am on "Course 1" course homepage
    Then I should see "Test Kahoodle (copy)"
    # Verify the original activity still has its questions.
    When I am on the "Test Kahoodle" "kahoodle activity" page
    And I navigate to "Questions" in current page administration
    Then I should see "What is the capital of France?"
    And I should see "What is 2 + 2?"
    And I should see "Which planet is largest?"
    # Verify the duplicated activity has the same questions.
    When I am on the "Test Kahoodle (copy)" "kahoodle activity" page
    And I navigate to "Questions" in current page administration
    Then I should see "What is the capital of France?"
    And I should see "What is 2 + 2?"
    And I should see "Which planet is largest?"
