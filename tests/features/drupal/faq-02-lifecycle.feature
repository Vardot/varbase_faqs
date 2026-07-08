Feature: Authoring an FAQ node
  As a content editor
  I want to create an FAQ node with a question and an answer
  So that visitors can find the answer on the FAQ page.

  # Meaningful, book-style: walk the real create lifecycle through the node-add
  # form, confirm the save, then prove the new question is actually listed on
  # the FAQ page (/faq-page). The FAQ node's own canonical page is intentionally
  # NOT asserted here because Varbase configures Rabbit Hole to 301-redirect faq
  # nodes to /faqs; the listing page is the audience-facing surface.

  Scenario: An author creates an FAQ and it appears on the FAQ page
    Given I am a logged in user with the "Webmaster" user
    When I create an FAQ node titled "Can I change my subscription plan?" answered "Yes. Open your Account settings and choose a new plan at any time."
    Then I should see "has been created"
    When I go to "/faq-page"
    Then I should see "Frequently Asked Questions"
    And I should see "Can I change my subscription plan?"
