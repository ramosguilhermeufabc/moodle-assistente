@local @local_assistente
Feature: The assistant has no chat
  In order to keep the assistant to the fixed queries
  As a teacher
  I need no free text field in any tab

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

  Scenario: No tab has a field to type a question
    Given the following "local_assistente > configs" exist:
      | course | enabled |
      | C1     | 1       |
    And I log in as "teacher1"
    When I open the "grades" tab of the assistant in course "C1"
    Then "textarea" "css_element" should not exist in the "region-main" "region"
    And "input[type=text]" "css_element" should not exist in the "region-main" "region"
    When I open the "synthesis" tab of the assistant in course "C1"
    Then "textarea" "css_element" should not exist in the "region-main" "region"
    And "input[type=text]" "css_element" should not exist in the "region-main" "region"
    When I open the "report" tab of the assistant in course "C1"
    Then "textarea" "css_element" should not exist in the "region-main" "region"
