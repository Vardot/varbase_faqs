<?php

namespace Drupal\faq\Controller;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Query\Condition;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\faq\FaqHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller routines for FAQ routes.
 */
class FaqController extends ControllerBase {

  /**
   * The active database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The link generator service.
   *
   * @var \Drupal\Core\Utility\LinkGeneratorInterface
   */
  protected $linkGenerator;

  /**
   * Constructs a FaqController object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The active database connection.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The configuration factory.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager service.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $linkGenerator
   *   The link generator service.
   */
  public function __construct(
    Connection $database,
    ConfigFactoryInterface $config,
    RendererInterface $renderer,
    EntityTypeManagerInterface $entityTypeManager,
    LanguageManagerInterface $languageManager,
    LinkGeneratorInterface $linkGenerator,
  ) {
    $this->database = $database;
    $this->config = $config;
    $this->renderer = $renderer;
    $this->entityTypeManager = $entityTypeManager;
    $this->languageManager = $languageManager;
    $this->linkGenerator = $linkGenerator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
    // Load the service required to construct this class.
      $container->get('database'),
      $container->get('config.factory'),
      $container->get('renderer'),
      $container->get('entity_type.manager'),
      $container->get('language_manager'),
      $container->get('link_generator')
    );
  }

  /**
   * Function to display the faq page.
   *
   * @param int $tid
   *   Default is 0, determines if the questions and answers on the page
   *   will be shown according to a category or non-categorized.
   * @param string $faq_display
   *   Optional parameter to override default question layout setting.
   * @param string $category_display
   *   Optional parameter to override default category layout setting.
   *
   * @return array
   *   The page with FAQ questions and answers.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   */
  public function faqPage($tid = 0, $faq_display = '', $category_display = '') {
    $faq_settings = $this->config->get('faq.settings');

    $output = $output_answers = '';

    $build = [];
    $build['#type'] = 'markup';
    $build['#attached']['library'][] = 'faq/faq-css';

    $build['#title'] = $faq_settings->get('title');

    if (!$this->moduleHandler()->moduleExists('taxonomy')) {
      $tid = 0;
    }

    $faq_display = $faq_settings->get('display');
    $use_categories = $faq_settings->get('use_categories');
    $category_display = $faq_settings->get('category_display');
    // If taxonomy doesn't installed, do not use categories.
    if (!$this->moduleHandler()->moduleExists('taxonomy')) {
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
      $langcode = $this->languageManager->getCurrentLanguage()->getId();
      $default_sorting = $faq_settings->get('default_sorting');
      $query = $this->database->select('node', 'n');
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
      // Works, but involves variable concatenation - safe though, since
      // $default_weight is an integer.
      $query->addExpression("COALESCE(w.weight, $default_weight)", 'effective_weight');
      // Doesn't work in Postgres.
      // $query->addExpression('COALESCE(w.weight, CAST(:default_weight as SIGNED))', 'effective_weight', array(':default_weight' => $default_weight));.
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
      foreach ($data as &$node) {
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
      $output = $this->renderer->render($questions_to_render);
    }

    // Categorize questions.
    else {
      $hide_child_terms = $faq_settings->get('hide_child_terms');

      // If we're viewing a specific category/term.
      if (!empty($tid)) {
        if ($term = Term::load($tid)) {
          $title = $faq_settings->get('title');

          $build['#title'] = ($title . ($title ? ' - ' : '') . $term->getName());

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
    $build['#markup'] = $this->renderer->render($markup);

    return $build;
  }

  /**
   * Define the elements for the FAQ Settings page - order tab.
   *
   * @param int|null $tid
   *   The category id of the FAQ page to reorder.
   *
   * @return array
   *   The form code, before being converted to HTML format.
   */
  public function orderPage($tid = NULL) {

    $faq_settings = $this->config->get('faq.settings');
    $build = [];

    $build['#attached']['library'][] = 'faq/faq-scripts';
    $build['#attached']['drupalSettings']['faqSettings']['hide_qa_accordion'] = $faq_settings->get('hide_qa_accordion');
    $build['#attached']['drupalSettings']['faqSettings']['category_hide_qa_accordion'] = $faq_settings->get('category_hide_qa_accordion');
    $build['#attached']['library'][] = 'faq/faq-css';

    $build['faq_order'] = $this->formBuilder()->getForm('Drupal\faq\Form\OrderForm');

    return $build;
  }

  /**
   * Renders the form for the FAQ Settings page - General tab.
   *
   * @return array
   *   The form code inside the $build array.
   */
  public function generalSettings() {
    $build = [];

    $build['faq_general_settings_form'] = $this->formBuilder()->getForm('Drupal\faq\Form\GeneralForm');

    return $build;
  }

  /**
   * Renders the form for the FAQ Settings page - Questions tab.
   *
   * @return array
   *   The form code inside the $build array.
   */
  public function questionsSettings() {
    $faq_settings = $this->config->get('faq.settings');

    $build = [];

    $build['#attached']['library'][] = 'faq/faq-scripts';
    $build['#attached']['drupalSettings']['faqSettings']['hide_qa_accordion'] = $faq_settings->get('hide_qa_accordion');
    $build['#attached']['drupalSettings']['faqSettings']['category_hide_qa_accordion'] = $faq_settings->get('category_hide_qa_accordion');

    $build['faq_questions_settings_form'] = $this->formBuilder()->getForm('Drupal\faq\Form\QuestionsForm');

    return $build;
  }

  /**
   * Renders the form for the FAQ Settings page - Categories tab.
   *
   * @return array
   *   The form code inside the $build array.
   */
  public function categoriesSettings() {
    $faq_settings = $this->config->get('faq.settings');

    $build = [];

    $build['#attached']['library'][] = 'faq/faq-scripts';
    $build['#attached']['drupalSettings']['faqSettings']['hide_qa_accordion'] = $faq_settings->get('hide_qa_accordion');
    $build['#attached']['drupalSettings']['faqSettings']['category_hide_qa_accordion'] = $faq_settings->get('category_hide_qa_accordion');

    if (!$this->moduleHandler()->moduleExists('taxonomy')) {
      $this->messenger()->addError($this->t('Categorization of questions will not work without the "taxonomy" module being enabled.'));
    }

    $build['faq_categories_settings_form'] = $this->formBuilder()->getForm('Drupal\faq\Form\CategoriesForm');

    return $build;
  }

  /* ****************************************************************
   * PRIVATE HELPER FUCTIONS
   * *************************************************************** */

  /**
   * Display FAQ questions and answers filtered by category.
   *
   * @param string $faq_display
   *   Define the way the FAQ is being shown; can have the values:
   *   'questions top', 'hide answers', 'questions inline', 'new page'.
   * @param string $category_display
   *   The layout of categories which should be used.
   * @param \Drupal\taxonomy\TermInterface $term
   *   The category / term to display FAQs for.
   * @param int $display_header
   *   Set if the header will be shown or not.
   * @param string $output
   *   Reference which holds the content of the page, HTML formatted.
   * @param string $output_answers
   *   Reference which holds the answers from the FAQ, when showing questions
   *   on top.
   */
  private function _displayFaqByCategory($faq_display, $category_display, $term, $display_header, &$output, &$output_answers) {
    $langcode = $this->languageManager->getCurrentLanguage()->getId();
    $default_sorting = $this->config->get('faq.settings')->get('default_sorting');

    $term_id = $term->id();

    $query = $this->database->select('node', 'n');
    $query->join('node_field_data', 'd', 'd.nid = n.nid');
    $query->innerJoin('taxonomy_index', 'ti', 'n.nid = ti.nid');
    $query->leftJoin('faq_weights', 'w', 'w.tid = ti.tid AND n.nid = w.nid');
    $query->fields('n', ['nid'])
      ->condition('n.type', 'faq')
      ->condition('d.langcode', $langcode)
      ->condition('d.status', 1)
      ->condition("ti.tid", $term_id)
      ->addTag('node_access');

    $default_weight = 0;
    if ($default_sorting == 'ASC') {
      $default_weight = 1000000;
    }
    // Works, but involves variable concatenation - safe though, since
    // $default_weight is an integer.
    $query->addExpression("COALESCE(w.weight, $default_weight)", 'effective_weight');
    // Doesn't work in Postgres.
    // $query->addExpression('COALESCE(w.weight, CAST(:default_weight as SIGNED))', 'effective_weight', array(':default_weight' => $default_weight));.
    $query->orderBy('effective_weight', 'ASC')
      ->orderBy('d.sticky', 'DESC');
    if ($default_sorting == 'ASC') {
      $query->orderBy('d.created', 'ASC');
    }
    else {
      $query->orderBy('d.created', 'DESC');
    }

    // We only want the first column, which is nid, so that we can load all
    // related nodes.
    $nids = $query->execute()->fetchCol();
    $data = Node::loadMultiple($nids);
    foreach ($data as &$node) {
      $node = ($node->hasTranslation($langcode)) ? $node->getTranslation($langcode) : $node;
    }

    // Handle indenting of categories.
    $depth = 0;
    if (!isset($term->depth)) {
      $children = $this->entityTypeManager->getStorage('taxonomy_term')->loadChildren($term->id());
      $term->depth = count($children);
    }
    while ($depth < $term->depth) {
      $display_header = 1;
      $indent = '<div class="faq-category-indent">';
      $output .= $indent;
      $depth++;
    }

    // Set up the class name for hiding the q/a for a category if required.
    $faq_class = "faq-qa";
    if ($category_display == "hide_qa") {
      $faq_class = "faq-qa-hide";
    }

    $output_render = $output_answers_render = [
      '#data' => $data,
      '#display_header' => $display_header,
      '#category_display' => $category_display,
      '#term' => $term,
      '#class' => $faq_class,
      '#parent_term' => $term,
    ];

    switch ($faq_display) {
      case 'questions_top':
        $output_render['#theme'] = 'faq_category_questions_top';
        $output .= $this->renderer->render($output_render);
        $output_answers_render['#theme'] = 'faq_category_questions_top_answers';
        $output_answers .= $this->renderer->render($output_answers_render);
        break;

      case 'hide_answer':
        $output_render['#theme'] = 'faq_category_hide_answer';
        $output .= $this->renderer->render($output_render);
        break;

      case 'questions_inline':
        $output_render['#theme'] = 'faq_category_questions_inline';
        $output .= $this->renderer->render($output_render);
        break;

      case 'new_page':
        $output_render['#theme'] = 'faq_category_new_page';
        $output .= $this->renderer->render($output_render);
        break;
    }
    // Handle indenting of categories.
    while ($depth > 0) {
      $output .= '</div>';
      $depth--;
    }
  }

  /**
   * Returns a structured array of terms indented according to the term depth.
   *
   * @param string $vid
   *   Vocabulary id.
   * @param int $tid
   *   Term id.
   *
   * @return array
   *   An array of a list of terms indented according to the term depth.
   */
  private function _getIndentedFaqTerms($vid, $tid) {
    // If ($this->moduleHandler()->moduleExists('pathauto')) {
    // pathauto does't exists in D8 yet
    // }.
    $faq_settings = $this->config->get('faq.settings');

    $display_faq_count = $faq_settings->get('count');
    $hide_child_terms = $faq_settings->get('hide_child_terms');

    $items = [];
    $tree = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree($vid, $tid, 1, TRUE);

    foreach ($tree as $term) {
      $term_id = $term->id();
      $tree_count = FaqHelper::taxonomyTermCountNodes($term_id);

      if ($tree_count) {
        // Get term description.
        $desc = '';
        $term_description = $term->getDescription();
        if (!empty($term_description)) {
          $desc = '<div class="faq-qa-description">';
          $desc .= $term_description . "</div>";
        }

        $query = $this->database->select('node', 'n');
        $query->join('node_field_data', 'd', 'n.nid = d.nid');
        $query->innerJoin('taxonomy_index', 'ti', 'n.nid = ti.nid');
        $term_node_count = $query->condition('d.status', 1)
          ->condition('n.type', 'faq')
          ->condition("ti.tid", $term_id)
          ->addTag('node_access')
          ->countQuery()
          ->execute()
          ->fetchField();

        if ($term_node_count > 0) {
          $path = Url::fromUserInput('/faq-page/' . $term_id);

          // Pathauto is not exists in D8 yet
          // if (!\Drupal::service('path.alias_manager.cached')->getPathAlias(arg(0) . '/' . $tid) && $this->moduleHandler()->moduleExists('pathauto')) {
          // }.
          if ($display_faq_count) {
            $count = $term_node_count;
            if ($hide_child_terms) {
              $count = $tree_count;
            }
            $cur_item = $this->linkGenerator->generate($term->getName(), $path) . " ($count) " . $desc;
          }
          else {
            $cur_item = $this->linkGenerator->generate($term->getName(), $path) . $desc;
          }
        }
        else {
          $cur_item = $term->getName() . $desc;
        }
        if (!empty($term_image)) {
          $cur_item .= '<div class="clear-block"></div>';
        }

        $term_items = [];
        if (!$hide_child_terms) {
          $term_items = $this->_getIndentedFaqTerms($vid, $term_id);
        }
        $items[] = [
          "item" => $cur_item,
          "children" => $term_items,
        ];
      }
    }

    return $items;
  }

  /**
   * Renders the output of getIntendedFaqTerms to HTML list.
   *
   * @param array $items
   *   The structured array made by getIntendedTerms function.
   * @param string $list_style
   *   List style type: ul or ol.
   *
   * @return string
   *   HTML formatted output.
   */
  private function _renderCategoriesToList($items, $list_style) {

    $list = [];

    foreach ($items as $item) {
      $pre = '';
      if (!empty($item['children'])) {
        $pre = $this->_renderCategoriesToList($item['children'], $list_style);
      }
      $list[] = new FormattableMarkup($item['item'] . $pre, []);
    }

    $render = [
      '#theme' => 'item_list',
      '#items' => $list,
      '#list_style' => $list_style,
    ];

    return $this->renderer->render($render);
  }

}
