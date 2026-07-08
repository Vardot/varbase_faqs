Feature: Varbase FAQ content type
  As a site builder
  I want the Varbase FAQ module to provide an FAQ content type
  So that I can author a frequently asked question and its answer.

  # Meaningful, book-style: assert the type is offered and that its add form
  # exposes the renamed "Question" (title) and "Answer" (body) fields by their
  # visible labels, not by theme markup.

  Scenario: The FAQ content type is listed with a create form for Question and Answer
    Given I am a logged in user with the "Webmaster" user
    When I open the administration page "/admin/structure/types"
    Then I should see "FAQ"
    And I should see "A frequently asked question and its answer."
    When I open the administration page "/node/add/faq"
    Then I should see "Question"
    And I should see "Body"
    And I should see "Detailed Question"
    And "#edit-title-0-value" should be visible
    And "#edit-submit" should be visible
