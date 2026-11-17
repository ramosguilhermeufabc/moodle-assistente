@local @local_assistente
Feature: Access to the assistant
  In order to protect the data of the students
  As a teacher
  I need the assistant to be available only to who has the capability

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

  Scenario: A teacher opens the assistant from the navigation of the course
    Given I am on the "C1" course page logged in as "teacher1"
    Then I should see "Assistant" in the ".secondary-navigation" "css_element"
    When I click on "Assistant" "link" in the ".secondary-navigation" "css_element"
    Then I should see "Grades"
    And I should see "Report"

  Scenario: A student does not see the entry and is denied by the URL
    Given I am on the "C1" course page logged in as "student1"
    Then I should not see "Assistant" in the ".secondary-navigation" "css_element"
    Then the "grades" tab of the assistant in course "C1" is denied

  Scenario: Who can only view does not see the configuration tab
    Given the following "users" exist:
      | username | firstname | lastname | email                | lang |
      | teacher2 | Teacher   | Two      | teacher2@example.com | en   |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | teacher2 | C1     | teacher |
    And the following "permission overrides" exist:
      | capability              | permission | role    | contextlevel | reference |
      | local/assistente:view   | Allow      | teacher | Course       | C1        |
    When I log in as "teacher2"
    And I open the "grades" tab of the assistant in course "C1"
    Then I should see "Grades"
    And I should not see "Configuration" in the "ul.nav-tabs.mb-3" "css_element"
    Then the configuration of the assistant in course "C1" is denied

  Scenario: The editing teacher sees the configuration tab
    Given I log in as "teacher1"
    When I open the "grades" tab of the assistant in course "C1"
    Then I should see "Configuration" in the "ul.nav-tabs.mb-3" "css_element"
