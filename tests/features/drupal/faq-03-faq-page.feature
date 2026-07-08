Feature: The FAQ page renders published questions
  As a user who can view the FAQ page
  I want /faq-page to list the published FAQ questions
  So that I can browse the frequently asked questions.

  # Meaningful, book-style: with a published FAQ node seeded by the CI recipe
  # (title "How do I reset my password?"), a user with "view faq page" sees the
  # page title and the seeded question. Default display is "hide_answer"
  # (accordion), so the QUESTION is visible; the answer is revealed on click, so
  # we assert the question label — the reliable, visible behaviour.

  Scenario: A permitted user sees the FAQ page listing the seeded question
    Given I am a logged in user with the "Webmaster" user
    When I go to "/faq-page"
    Then I should see "Frequently Asked Questions"
    And I should see "How do I reset my password?"

  # OPTIONAL public FAQ page: only holds when the CI recipe grants "view faq
  # page" to the anonymous role. Left @wip so the default-config denial matrix
  # (faq-05) stays green; unwip together with granting the permission to anon.
  @wip
  Scenario: An anonymous visitor sees the public FAQ page
    Given I am an anonymous visitor
    When I go to "/faq-page"
    Then I should see "Frequently Asked Questions"
    And I should see "How do I reset my password?"
