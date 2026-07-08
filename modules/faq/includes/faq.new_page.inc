<?php

/**
 * @file
 * FAQ page callbacks for the "new page" layouts.
 */

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\faq\FaqHelper;
use Drupal\Component\Utility\Html;

/**
 * Create FAQ page if set to show the answer in a new page.
 *
 * @param &$variables
 *   Array reference of arguments given to the theme() function.
 */
function template_preprocess_faq_new_page(&$variables) {
  $faq_settings = \Drupal::config('faq.settings');
  $items = [];
  $data = $variables['data'];

  foreach ($data as $node) {
    $items[] = Link::fromTextAndUrl($node->getTitle(), $url = $node->toUrl())->toString();
  }

  $list_style = $faq_settings->get('question_listing');
  $variables['list_style'] = $list_style;
  $variables['list_items'] = $items;

  $item_list = [
    '#theme' => 'item_list',
    '#title' => NULL,
    '#list_type' => $list_style,
    '#items' => $items,
  ];

  $variables['list'] = new FormattableMarkup(\Drupal::service('renderer')->render($item_list), []);
}

/**
 * Create categorized FAQ page if set to show answer in a new page.
 *
 * @param &$variables
 *   Array reference of arguments given to the theme() function.
 */
function template_preprocess_faq_category_new_page(&$variables) {
  $faq_settings = \Drupal::config('faq.settings');
  $data = $variables['data'];
  $category_display = $variables['category_display'];
  $term = $variables['term'];
  $term_id = $term->id();
  $parent_term = $variables['parent_term'];
  $class = $variables['class'];
  $this_page = Url::fromRoute('<current>');

  // Fetch configuration.
  $display_faq_count = $faq_settings->get('count');
  $hide_child_terms = $faq_settings->get('hide_child_terms');
  $show_term_page_children = $faq_settings->get('show_term_page_children');

  // Initialise some variables.
  $get_child_terms = 0;
  // Check if we're on a faq page.
  if ($id = FaqHelper::searchInArgs('faq-page')) {
    $next_id = ($id) + 1;
    // Check if we're on a categorized faq page.
    if (is_numeric(FaqHelper::arg($next_id))) {
      $get_child_terms = FaqHelper::arg($next_id);
    }
  }
  // Force some settings in case we're processing a special faq question list
  // created by a custom call to faq_page().
  elseif (!empty($parent_term)) {
    $get_child_terms = $parent_term->id();
    $show_term_page_children = TRUE;
  }

  $default_sorting = $faq_settings->get('default_sorting');
  $default_weight = 0;
  if ($default_sorting != 'DESC') {
    $default_weight = 1000000;
  }

  // Get number of questions, and account for hidden sub-categories.
  $count = 0;
  if ($display_faq_count && $hide_child_terms) {
    $count = FaqHelper::taxonomyTermCountNodes($term_id);
  }
  $variables['display_faq_count'] = $display_faq_count;

  // Get taxonomy image.
  $variables['term_image'] = '';
  // taxonomy_image does not exists in D8 yet
  // if (module_exists('taxonomy_image')) {
  // $variables['term_image'] = taxonomy_image_display($term->tid, array('class' => 'faq-tax-image'));
  // }
  // Configure header.
  $variables['category_depth'] = $term->depth;
  if ($category_display == 'hide_qa') {
    $variables['header_title'] = Link::fromTextAndUrl($term->getName(), Url::fromUserInput('/faq/' . $term_id))->toString();
  }
  else {
    $variables['header_title'] = $term->getName();
  }

  // Configure category description.
  $variables['description'] = Html::escape($term->getDescription());

  // Get list of sub-categories if necessary.
  $child_categories = [];
  if (($show_term_page_children || $hide_child_terms) && $category_display == 'new_page') {
    $child_categories = FaqHelper::viewChildCategoryHeaders($term);
  }
  $variables['subcat_list'] = $child_categories;
  $variables['subcat_list_style'] = $faq_settings->get('category_listing');

  // Configure class (faq-qa or faq-qa-hide).
  if ($get_child_terms == $term_id) {
    $variables['container_class'] = 'faq-qa';
  }
  else {
    $variables['container_class'] = $class;
  }

  // Configure sub-category bodies (theme recursively).
  $variables['subcat_body_list'] = [];
  if (($get_child_terms && $category_display == 'categories_inline') || ((($show_term_page_children && $this_page != 'faq-page') || $hide_child_terms) && $category_display == 'hide_qa')) {
    $variables['subcat_body_list'] = FaqHelper::getChildCategoriesFaqs($term, 'faq_category_new_page', $default_weight, $default_sorting, $category_display, $variables['class'], $parent_term);
  }

  if (!count($data)) {
    $variables['question_count'] = $count;
    $variables['nodes'] = [];
    return;
  }

  $nodes = [];
  foreach ($data as $node) {
    if (!$hide_child_terms) {
      $count++;
    }
    $nodes[] = Link::fromTextAndUrl($node->getTitle(), $node->toUrl())->toString();
  }
  $variables['question_list'] = $nodes;
  $variables['question_list_style'] = $faq_settings->get('question_listing');
  $variables['question_count'] = $count;
}
