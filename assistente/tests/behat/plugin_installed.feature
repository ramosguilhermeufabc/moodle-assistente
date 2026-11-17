@local @local_assistente
Feature: The Assistente plugin is installed
  In order to know that the Behat runner works with the plugin
  As an administrator
  I need to see the plugin in the list of installed plugins

  Scenario: The plugin is listed among the local plugins
    Given I log in as "admin"
    When I navigate to "Plugins > Plugins overview" in site administration
    Then I should see "local_assistente"
