@local @local_assistente
Feature: Criteria are read only after they are saved
  In order to keep the criteria of a course stable
  As a teacher
  I need the saved criteria to be read only

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                | lang |
      | teacher1 | Teacher   | One      | teacher1@example.com | en   |
      | student1 | Student   | One      | student1@example.com | en   |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |

  Scenario: The first save sets the criteria and the next time they are read only
    Given I log in as "teacher1"
    When I open the configuration of the assistant in course "C1"
    And I set the following fields to these values:
      | Enable the assistant in this course | 1  |
      | Activity criterion (%)              | 60 |
      | Attendance criterion (%)            | 70 |
    And I press "Save changes"
    Then I should see "Changes saved"
    When I open the configuration of the assistant in course "C1"
    Then I should see "The criteria are saved. Only an administrator can correct them"
    And the "Activity criterion (%)" "field" should be disabled
    And the field "Activity criterion (%)" matches value "60"

  Scenario: A manager corrects criteria already saved by the teacher
    Given the following "users" exist:
      | username | firstname | lastname | email                 | lang |
      | manager1 | Manager   | One      | manager1@example.com  | en   |
    And the following "system role assigns" exist:
      | user     | role    |
      | manager1 | manager |
    And I log in as "teacher1"
    And I open the configuration of the assistant in course "C1"
    And I set the following fields to these values:
      | Enable the assistant in this course | 1  |
      | Activity criterion (%)              | 60 |
      | Attendance criterion (%)            | 70 |
    And I press "Save changes"
    And I log out
    When I log in as "manager1"
    And I open the configuration of the assistant in course "C1"
    And I set the following fields to these values:
      | Activity criterion (%) | 50 |
    And I press "Save changes"
    Then I should see "Changes saved"
    When I open the configuration of the assistant in course "C1"
    Then the field "Activity criterion (%)" matches value "50"

  Scenario: An invalid percentage is refused
    Given I log in as "teacher1"
    When I open the configuration of the assistant in course "C1"
    And I set the following fields to these values:
      | Activity criterion (%)   | 100.5 |
      | Attendance criterion (%) | 70    |
    And I press "Save changes"
    Then I should see "Enter a number from 0 to 100 with at most three decimals."
