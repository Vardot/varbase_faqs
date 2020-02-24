<?php

namespace Drupal\varbase_faqs\Plugin\Block;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\faq\FaqHelper;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Database\Query\Condition;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Cache\Cache;

/**
 * Provides a simple block.
 *
 * @Block(
 *   id = "faqs_list",
 *   admin_label = @Translation("FAQs List")
 * )
 */
class FaqPageBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Active database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Contains the configuration object factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new FAQs List Block.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection to be used.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $database, ConfigFactoryInterface $config_factory, ModuleHandlerInterface $module_handler, LanguageManagerInterface $language_manager, EntityTypeManagerInterface $entity_type_manager, RendererInterface $renderer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->database = $database;
    $this->configFactory = $config_factory;
    $this->moduleHandler = $module_handler;
    $this->languageManager = $language_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
      $container->get('config.factory'),
      $container->get('module_handler'),
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
      $container->get('renderer')
    );
  }

  /**
   * Implements \Drupal\block\BlockBase::blockBuild().
   */
  public function build() {
    $tid = 0;
    $faq_display = '';
    $category_display = '';
    $faq_settings = $this->configFactory->get('faq.settings');

    $output = $output_answers = '';

    $build = [];
    $build['#type'] = 'markup';
    $build['#attached']['library'][] = 'faq/faq-css';

    $build['#title'] = $faq_settings->get('title');

    if (!$this->moduleHandler->moduleExists('taxonomy')) {
      $tid = 0;
    }

    $faq_display = $faq_settings->get('display');
    $use_categories = $faq_settings->get('use_categories');
    $category_display = $faq_settings->get('category_display');
    // If taxonomy doesn't installed, do not use categories.
    if (!$this->moduleHandler->moduleExists('taxonomy')) {
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
      $data = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);
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
        if ($term = $this->entityTypeManager->getStorage('taxonomy_term')->load($tid)) {
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
      $vocabularies = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->loadMultiple();
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
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(parent::getCacheTags(), ['node_list']);
  }

}
