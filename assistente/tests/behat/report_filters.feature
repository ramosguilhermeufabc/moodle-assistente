@local @local_assistente
Feature: Filters of the report and of its history
  In order to find a version of the report
  As a teacher
  I need to filter the versions by number and by the day they were executed

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                | lang |
      | teacher1 | Teacher   | One      | teacher1@example.com | en   |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "local_assistente > configs" exist:
      | course | enabled |
      | C1     | 1       |
    # Executions at 12:00 UTC of 10, 11 and 12 November 2026: the same calendar day in the time zones of the site (Sao Paulo) and of the test site (Perth).
    And the following "local_assistente > report_versions" exist:
      | course | created_at |
      | C1     | 1794312000 |
      | C1     | 1794398400 |
      | C1     | 1794484800 |
    And I log in as "teacher1"

  Scenario: The history lists every version and the period keeps the days chosen, both inclusive
    When I open the "history" tab of the assistant in course "C1"
    Then I should see "10/11/26" in the ".local-assistente-history table" "css_element"
    And I should see "11/11/26" in the ".local-assistente-history table" "css_element"
    And I should see "12/11/26" in the ".local-assistente-history table" "css_element"
    When I set the following fields to these values:
      | id_from_enabled | 1  |
      | id_from_day     | 11 |
      | id_from_month   | 11 |
      | id_from_year    | 2026 |
      | id_to_enabled   | 1  |
      | id_to_day       | 12 |
      | id_to_month     | 11 |
      | id_to_year      | 2026 |
    And I press "Apply filters"
    Then I should not see "10/11/26" in the ".local-assistente-history table" "css_element"
    And I should see "11/11/26" in the ".local-assistente-history table" "css_element"
    And I should see "12/11/26" in the ".local-assistente-history table" "css_element"

  Scenario: A period with no execution says that no version matches
    When I open the "history" tab of the assistant in course "C1"
    And I set the following fields to these values:
      | id_from_enabled | 1  |
      | id_from_day     | 1  |
      | id_from_month   | 1  |
      | id_from_year    | 2025 |
      | id_to_enabled   | 1  |
      | id_to_day       | 31 |
      | id_to_month     | 1  |
      | id_to_year      | 2025 |
    And I press "Apply filters"
    Then I should see "No version matches the filters."
    And "table" "css_element" should not exist in the ".local-assistente-history" "css_element"

  Scenario: A last day before the first day is refused by the form and shows no result
    When I open the "history" tab of the assistant in course "C1"
    And I set the following fields to these values:
      | id_from_enabled | 1  |
      | id_from_day     | 12 |
      | id_from_month   | 11 |
      | id_from_year    | 2026 |
      | id_to_enabled   | 1  |
      | id_to_day       | 10 |
      | id_to_month     | 11 |
      | id_to_year      | 2026 |
    And I press "Apply filters"
    Then I should see "The last day must not be before the first day."
    And ".local-assistente-history" "css_element" should not exist
    And I should not see "No version matches the filters."

  Scenario: The report shows the current version and the version chosen in the list
    When I open the "report" tab of the assistant in course "C1"
    Then I should see "Current version: 3"
    When I set the field "Report version" to "2"
    And I press "Apply filters"
    Then I should see "Current version: 2"
