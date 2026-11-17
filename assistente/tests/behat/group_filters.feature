@local @local_assistente
Feature: Group filter in the individual queries
  In order to see only the students of my groups
  As a teacher in separate groups
  I need the filter to offer only my groups

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                | lang |
      | teacher1 | Teacher   | One      | teacher1@example.com | en   |
      | teacher2 | Teacher   | Two      | teacher2@example.com | en   |
    And the following "courses" exist:
      | fullname | shortname | groupmode | groupmodeforce |
      | Course 1 | C1        | 1         | 1              |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | teacher2 | C1     | teacher        |
    And the following "groups" exist:
      | name    | course | idnumber |
      | Group A | C1     | GA       |
      | Group B | C1     | GB       |
    And the following "group members" exist:
      | user     | group |
      | teacher2 | GA    |
    And the following "permission overrides" exist:
      | capability            | permission | role    | contextlevel | reference |
      | local/assistente:view | Allow      | teacher | Course       | C1        |
    And the following "local_assistente > configs" exist:
      | course | enabled |
      | C1     | 1       |

  Scenario: A teacher in separate groups is offered only the own group
    Given I log in as "teacher2"
    When I open the "grades" tab of the assistant in course "C1"
    Then the "Group" select box should contain "Group A"
    And the "Group" select box should not contain "Group B"

  Scenario: The editing teacher is offered every group, and the aggregate tabs have no group filter
    Given I log in as "teacher1"
    When I open the "grades" tab of the assistant in course "C1"
    Then the "Group" select box should contain "Group A"
    And the "Group" select box should contain "Group B"
    When I open the "forums" tab of the assistant in course "C1"
    Then "Group" "select" should not exist

  Scenario: The report offers the group for its individual blocks and the history does not
    Given I log in as "teacher2"
    When I open the "report" tab of the assistant in course "C1"
    Then the "Group" select box should contain "Group A"
    And the "Group" select box should not contain "Group B"
    When I open the "history" tab of the assistant in course "C1"
    Then "Group" "select" should not exist
