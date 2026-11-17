@local @local_assistente
Feature: Language of the interface and of the generated content
  In order to read the interface in my language and the content in the language it was generated
  As a teacher
  I need the labels to follow my language and the stored content to stay as it was

  Scenario: Interface in English and a synthesis stored in Portuguese
    Given the following "users" exist:
      | username | firstname | lastname | email                | lang |
      | teacher1 | Teacher   | One      | teacher1@example.com | en   |
    And the following "courses" exist:
      | fullname | shortname | lang  |
      | Course 1 | C1        | pt_br |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "local_assistente > configs" exist:
      | course | enabled |
      | C1     | 1       |
    And the following "local_assistente > syntheses" exist:
      | course | section | content                                |
      | C1     | 1       | Síntese gerada em português do Brasil. |
    And I log in as "teacher1"
    When I open the "synthesis" tab of the assistant in course "C1"
    Then I should see "Síntese gerada em português do Brasil."
    And I should see "Content"
    And I should see "Section"
