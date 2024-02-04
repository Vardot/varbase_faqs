<?php

/**
 * @file
 * Contains List of all varbase_faqs helpers.
 *
 * Add custom needed helper functions.
 */

use Vardot\Installer\ModuleInstallerFactory;

/**
 * Configure FAQ settings.
 */
function varbase_faqs__configure_faq_settings() {
  $faq_settings = \Drupal::configFactory()->getEditable('faq.settings');

  $faq_settings->set('display', 'hide_answer')
    ->set('show_expand_all', 1)
    ->set('back_to_top', '')
    ->set('disable_node_links', 1)
    ->set('category_display', 'none')
    ->save();

  $varbase_faqs_config = [
    'rabbit_hole.behavior_settings.node.faq',
  ];

  ModuleInstallerFactory::importConfigsFromList('varbase_faqs', $varbase_faqs_config, 'config/optional');
}
