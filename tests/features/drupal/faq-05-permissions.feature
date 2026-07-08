Feature: FAQ access control
  As a site owner
  I want the FAQ page and settings gated by permission
  So that only permitted users reach them.

  # Meaningful, book-style permission matrix against the shipped Varbase config:
  # anonymous has no "view faq page"; a plain authenticated user has no
  # "administer faq"; only the admin (Webmaster) reaches both the FAQ page and
  # the FAQ settings. Denials use the built-in navigation step (not the
  # admin-open step, which fails on access-denied by design).

  Scenario: Anonymous visitors are denied the FAQ page
    Given I am an anonymous visitor
    When I go to "/faq-page"
    Then I should see "Access denied"

  Scenario: A plain authenticated user is denied the FAQ page and the FAQ settings
    Given I am a logged in user with the "Authenticated" user
    When I go to "/faq-page"
    Then I should see "Access denied"
    When I go to "/admin/config/content/faq"
    Then I should see "Access denied"

  Scenario: The Webmaster reaches both the FAQ page and the FAQ settings
    Given I am a logged in user with the "Webmaster" user
    When I open the administration page "/faq-page"
    Then I should see "Frequently Asked Questions"
    When I open the administration page "/admin/config/content/faq"
    Then I should see "FAQ Description"
