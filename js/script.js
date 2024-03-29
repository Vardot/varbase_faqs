/**
 * @file
 * Behaviors for the varbase_faqs.
 */

(function ($, Drupal) {
  Drupal.behaviors.cat = {
    attach: function () {
      $(document).ready(function () {
        // Make the faqcategories active based on the current path.
        const path = window.location.pathname;
        const target = $('#block-faq-categories a[href="' + path + '"]');
        target.addClass("active");
      });
    }
  };
})(jQuery, window.Drupal);
