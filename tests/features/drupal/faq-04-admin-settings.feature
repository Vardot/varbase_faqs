Feature: FAQ admin settings
  As an FAQ administrator
  I want the FAQ settings organised into General, Questions and Categories tabs
  So that I can configure how questions and answers are presented.

  # Meaningful, book-style: each settings tab renders its own controls; assert a
  # distinctive visible label on every tab (not markup), and that the three tab
  # links are present on the General page.

  Scenario: The General settings page shows the FAQ configuration tabs and fields
    Given I am a logged in user with the "Webmaster" user
    When I open the administration page "/admin/config/content/faq"
    Then I should see "General"
    And I should see "Questions"
    And I should see "Categories"
    And I should see "FAQ Description"

  Scenario: The Questions settings page controls the question and answer layout
    Given I am a logged in user with the "Webmaster" user
    When I open the administration page "/admin/config/content/faq/questions"
    Then I should see "Page layout"
    And I should see "Question Label"
    And I should see "Answer Label"

  Scenario: The Categories settings page controls categorisation
    Given I am a logged in user with the "Webmaster" user
    When I open the administration page "/admin/config/content/faq/categories"
    Then I should see "Categorize questions"
    And I should see "Categories layout"
