<?php

namespace Drupal\varbase_faqs\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for the Varbase FAQs module.
 */
class VarbaseFaqsHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'faq_hide_answer' => [
        'template' => 'faq-hide-answer',
        'variables' => ['data' => NULL],
      ],
    ];
  }

}
