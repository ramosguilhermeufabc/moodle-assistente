@local @local_assistente
Feature: A course where the assistant is not enabled
  In order not to process a course that did not ask for it
  As a teacher
  I need every tab to say that the assistant is not enabled

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

  Scenario: A new course has the assistant disabled in every tab
    Given I log in as "teacher1"
    When I open the "grades" tab of the assistant in course "C1"
    Then I should see "assistant not enabled"
    And "table" "css_element" should not exist in the ".local-assistente-query" "css_element"
    When I open the "frequency" tab of the assistant in course "C1"
    Then I should see "assistant not enabled"
    When I open the "forums" tab of the assistant in course "C1"
    Then I should see "assistant not enabled"
    When I open the "synthesis" tab of the assistant in course "C1"
    Then I should see "assistant not enabled"
    When I open the "report" tab of the assistant in course "C1"
    Then I should see "assistant not enabled"

  Scenario: A configured but disabled course also says so
    Given the following "local_assistente > configs" exist:
      | course | enabled |
      | C1     | 0       |
    And I log in as "teacher1"
    When I open the "materials" tab of the assistant in course "C1"
    Then I should see "assistant not enabled"
