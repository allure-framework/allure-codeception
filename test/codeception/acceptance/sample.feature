@FeatureTag
Feature: Calculate absolute number
  I need some simple feature to test

  Background:
    Given I have no input

  @ScenarioTag
  Scenario: negative number
    Given I have input as "-1"
    Then I should get output as 1

  Scenario: zero
    Given I have input as 0
    Then I should get output as 0

  Scenario Outline: positive number
    Given I have input as <in>
    Then I should get output as <out>

    Examples:
      | in | out |
      | 1  | 1   |
      | 2  | 2   |

  Scenario: various numbers
    Given I have inputs
      | num |
      | -1  |
      | 0   |
      | 1   |
    Then I should get non-negative outputs