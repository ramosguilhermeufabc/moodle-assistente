@local @local_assistente
Feature: Order of the lists
  In order to find a student quickly
  As a teacher
  I need the students with the same value to be listed by name

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

  Scenario: Students without data tie and are listed from A to Z
    Given the following "users" exist:
      | username | firstname | lastname | email                | lang |
      | carlos   | Carlos    | Silva    | carlos@example.com   | en   |
      | ana      | Ana       | Silva    | ana@example.com      | en   |
      | bia      | Bia       | Silva    | bia@example.com      | en   |
    And the following "course enrolments" exist:
      | user   | course | role    |
      | carlos | C1     | student |
      | ana    | C1     | student |
      | bia    | C1     | student |
    And the following "local_assistente > configs" exist:
      | course | enabled |
      | C1     | 1       |
    And I log in as "teacher1"
    When I open the "grades" tab of the assistant in course "C1"
    Then "Ana Silva" "text" should appear before "Bia Silva" "text"
    And "Bia Silva" "text" should appear before "Carlos Silva" "text"
    And "Carlos Silva" "text" should appear before "Student One" "text"
