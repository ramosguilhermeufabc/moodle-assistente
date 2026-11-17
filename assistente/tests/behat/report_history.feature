@local @local_assistente
Feature: Report and its history
  In order to follow the reports of the course
  As a teacher
  I need to see the current version and reach the history

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

  Scenario: The report shows the current version with a link to the history
    Given the following "local_assistente > configs" exist:
      | course | enabled |
      | C1     | 1       |
    And the following "local_assistente > report_versions" exist:
      | course |
      | C1     |
      | C1     |
    And I log in as "teacher1"
    When I open the "report" tab of the assistant in course "C1"
    Then I should see "Current version: 2"
    When I click on "See the history of the report" "link"
    Then I should see "History"
    And I should see "Version"
