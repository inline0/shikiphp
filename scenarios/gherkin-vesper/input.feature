Feature: User login
  As a registered user
  I want to log in
  So that I can access my account

  Scenario: Successful login
    Given I am on the login page
    When I enter "alice" and "secret"
    And I click "Sign in"
    Then I should see "Welcome, alice"

  Scenario Outline: Invalid credentials
    Given I am on the login page
    When I enter "<user>" and "<pass>"
    Then I should see an error
    Examples:
      | user  | pass  |
      | bob   | wrong |
      | ""    | ""    |
