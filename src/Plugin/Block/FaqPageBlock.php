<?php

namespace Drupal\varbase_faqs\Plugin\Block;

use Drupal\faq\FaqHelper;
use Drupal\Core\Block\BlockBase;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Database\Query\Condition;
use Drupal\node\Entity\Node;
use Drupal\Component\Render\FormattableMarkup;

/**
 * Provides a simple block.
 *
 * @Block(
 *   id = "faqs_list",
 *   admin_label = @Translation("FAQs List")
 * )
 */
class FaqPageBlock extends BlockBase {

  /**
   * Implements \Drupal\block\BlockBase::blockBuild().
   */
  public function build() {
    $tid = 0;
    $faq_display = '';
    $category_display = '';
    $faq_settings = \Drupal::configFactory()->get('faq.settings');

    $output = $output_answers = '';

    $build = [];
    $build['#type'] = 'markup';
    $build['#attached']['library'][] = 'faq/faq-css';

    $build['#title'] = $faq_settings->get('title');

    if (!\Drupal::moduleHandler()->moduleExists('taxonomy')) {
      $tid = 0;
    }

    $faq_display = $faq_settings->get('display');
    $use_categories = $faq_settings->get('use_categories');
    $category_display = $faq_settings->get('category_display');
    // If taxonomy doesn't installed, do not use categories.
    if (!\Drupal::moduleHandler()->moduleExists('taxonomy')) {
      $use_categories = FALSE;
    }

    if (($use_categories && $category_display == 'hide_qa') || $faq_display == 'hide_answer') {
      $build['#attached']['library'][] = 'faq/faq-scripts';
      $build['#attached']['drupalSettings']['faqSettings']['hide_qa_accordion'] = $faq_settings->get('hide_qa_accordion');
      $build['#attached']['drupalSettings']['faqSettings']['category_hide_qa_accordion'] = $faq_settings->get('category_hide_qa_accordion');
    }

    // Non-categorized questions and answers.
    if (!$use_categories || ($category_display == 'none' && empty($tid))) {
      if (!empty($tid)) {
        throw new NotFoundHttpException();
      }
      $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
      $default_sorting = $faq_settings->get('default_sorting');
      $query = \Drupal::database()->select('node', 'n');
      $weight_alias = $query->leftJoin('faq_weights', 'w', '%alias.nid=n.nid');
      $query->leftJoin('node_field_data', 'd', 'd.nid=n.nid');
      $db_or = new Condition('OR');
      $db_or->condition("$weight_alias.tid", 0)->isNull("$weight_alias.tid");
      $query
        ->fields('n', ['nid'])
        ->condition('n.type', 'faq')
        ->condition('d.langcode', $langcode)
        ->condition('d.status', 1)
        ->condition($db_or)
        ->addTag('node_access');

      $default_weight = 0;
      if ($default_sorting == 'ASC') {
        $default_weight = 1000000;
      }
      $query->addExpression("COALESCE(w.weight, $default_weight)", 'effective_weight');
      $query->orderBy('effective_weight', 'ASC')
        ->orderBy('d.sticky', 'DESC');
      if ($default_sorting == 'ASC') {
        $query->orderBy('d.created', 'ASC');
      }
      else {
        $query->orderBy('d.created', 'DESC');
      }

      // Only need the nid column.
      $nids = $query->execute()->fetchCol();
      $data = Node::loadMultiple($nids);
      foreach ($data as $key => &$node) {
        $node = ($node->hasTranslation($langcode)) ? $node->getTranslation($langcode) : $node;
      }

      $questions_to_render = [];
      $questions_to_render['#data'] = $data;

      switch ($faq_display) {
        case 'questions_top':
          $questions_to_render['#theme'] = 'faq_questions_top';
          break;

        case 'hide_answer':
          $questions_to_render['#theme'] = 'faq_hide_answer';
          break;

        case 'questions_inline':
          $questions_to_render['#theme'] = 'faq_questions_inline';
          break;

        case 'new_page':
          $questions_to_render['#theme'] = 'faq_new_page';
          break;
      } // End of switch.
      $output = \Drupal::service('renderer')->render($questions_to_render);
    }

    // Categorize questions.
    else {
      $hide_child_terms = $faq_settings->get('hide_child_terms');

      // If we're viewing a specific category/term.
      if (!empty($tid)) {
        if ($term = Term::load($tid)) {
          $title = $faq_settings->get('title');

          $build['#title'] = ($title . ($title ? ' - ' : '') . $this->t($term->getName()));

          $this->_displayFaqByCategory($faq_display, $category_display, $term, 0, $output, $output_answers);
          $to_render = [
            '#theme' => 'faq_page',
            '#content' => new FormattableMarkup($output, []),
            '#answers' => new FormattableMarkup($output_answers, []),
          ];
          $build['#markup'] = $this->renderer->render($to_render);
          return $build;
        }
        else {
          throw new NotFoundHttpException();
        }
      }

      $list_style = $faq_settings->get('category_listing');
      $vocabularies = Vocabulary::loadMultiple();
      $vocab_omit = $faq_settings->get('omit_vocabulary');
      $items = [];
      $vocab_items = [];
      foreach ($vocabularies as $vid => $vobj) {
        if (isset($vocab_omit[$vid]) && ($vocab_omit[$vid] !== 0)) {
          continue;
        }

        if ($category_display == "new_page") {
          $vocab_items = $this->_getIndentedFaqTerms($vid, 0);
          $items = array_merge($items, $vocab_items);
        }
        // Not a new page.
        else {
          if ($hide_child_terms && $category_display == 'hide_qa') {
            $tree = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree($vid, 0, 1, TRUE);
          }
          else {
            $tree = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree($vid, 0, NULL, TRUE);
          }
          foreach ($tree as $term) {
            switch ($category_display) {
              case 'hide_qa':
              case 'categories_inline':
                if (FaqHelper::taxonomyTermCountNodes($term->id())) {
                  $this->_displayFaqByCategory($faq_display, $category_display, $term, 1, $output, $output_answers);
                }
                break;
            }
          }
        }
      }

      if ($category_display == "new_page") {
        $output = $this->_renderCategoriesToList($items, $list_style);
      }
    }

    $faq_description = $faq_settings->get('description');

    $markup = [
      '#theme' => 'faq_page',
      '#content' => new FormattableMarkup($output, []),
      '#answers' => new FormattableMarkup($output_answers, []),
      '#description' => new FormattableMarkup($faq_description, []),
    ];
    $build['#markup'] = \Drupal::service('renderer')->render($markup);

    return $build;
  }

}
