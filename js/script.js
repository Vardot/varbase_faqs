/**
 * @file
 * Behaviors for the varbase_faqs.
 */

(function faqBehaviors($, Drupal) {
  Drupal.behaviors.cat = {
    attach() {
      $(document).ready(function highlightActiveFaqCategory() {
        // Make the faqcategories active based on the current path.
        const path = window.location.pathname;
        const target = $(`#block-faq-categories a[href="${path}"]`);
        target.addClass('active');
      });
    },
  };
})(jQuery, window.Drupal);
