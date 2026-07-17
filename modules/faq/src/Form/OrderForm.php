<?php

namespace Drupal\faq\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\faq\FaqHelper;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for reordering the FAQ-s.
 */
class OrderForm extends ConfigFormBase {

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
   * The active database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs an OrderForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager service.
   * @param \Drupal\Core\Database\Connection $database
   *   The active database connection.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    LanguageManagerInterface $languageManager,
    Connection $database,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->languageManager = $languageManager;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
    // Load the service required to construct this class.
      $container->get('entity_type.manager'),
      $container->get('language_manager'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'faq_order_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $category = NULL) {

    // Get category id from route values.
    if ($id = FaqHelper::searchInArgs('faq-page')) {
      $next_id = ($id) + 1;
      // Check if we're on a categorized faq page.
      if (is_numeric(FaqHelper::arg($next_id))) {
        $category = FaqHelper::arg($next_id);
      }
    }

    $faq_settings = $this->config('faq.settings');

    $use_categories = $faq_settings->get('use_categories');
    if (!$use_categories) {
      $step = "order";
    }
    elseif ($form_state->getValues() != NULL && empty($category)) {
      $step = "categories";
    }
    else {
      $step = "order";
    }
    $form['step'] = [
      '#type' => 'value',
      '#value' => $step,
    ];

    // Categorized q/a.
    if ($step == "categories") {

      // Get list of categories.
      $vocabularies = Vocabulary::loadMultiple();
      $options = [];
      foreach ($vocabularies as $vid => $vobj) {
        $tree = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree($vid);
        foreach ($tree as $term) {
          if (!FaqHelper::taxonomyTermCountNodes($term->tid)) {
            continue;
          }
          $options[$term->tid] = $term->name;
          $form['choose_cat']['faq_category'] = [
            '#type' => 'select',
            '#title' => $this->t('Choose a category'),
            '#description' => $this->t('Choose a category that you wish to order the questions for.'),
            '#options' => $options,
            '#multiple' => FALSE,
          ];

          $form['choose_cat']['search'] = [
            '#type' => 'submit',
            '#value' => $this->t('Search'),
            '#submit' => ['::orderSettingsChooseCatSubmit'],
          ];
        }
      }
    }
    else {
      $default_sorting = $faq_settings->get('default_sorting');
      $default_weight = 0;
      if ($default_sorting != 'DESC') {
        $default_weight = 1000000;
      }

      $options = [];
      if (!empty($form_state->getValue('faq_category'))) {
        $category = $form_state->getValue('faq_category');
      }

      $langcode = $this->languageManager->getCurrentLanguage()->getId();
      // Uncategorized ordering.
      $query = $this->database->select('node', 'n');
      $query->join('node_field_data', 'd', 'n.nid = d.nid');
      $query->fields('n', ['nid'])
        ->fields('d', ['title'])
        ->condition('n.type', 'faq')
        ->condition('d.langcode', $langcode)
        ->condition('d.status', 1)
        ->addTag('node_access');

      // Works, but involves variable concatenation - safe though, since
      // $default_weight is an integer.
      $query->addExpression("COALESCE(w.weight, $default_weight)", 'effective_weight');
      // Doesn't work in Postgres.
      // $query->addExpression('COALESCE(w.weight, CAST(:default_weight as SIGNED))', 'effective_weight', array(':default_weight' => $default_weight));.
      if (empty($category)) {
        $category = 0;
        $query->leftJoin('faq_weights', 'w', 'n.nid = %alias.nid AND %alias.tid = :category', [':category' => $category]);
        $query->orderBy('effective_weight', 'ASC')
          ->orderBy('d.sticky', 'DESC')
          ->orderBy('d.created', $default_sorting == 'DESC' ? 'DESC' : 'ASC');
      }
      // Categorized ordering.
      else {
        $query->innerJoin('taxonomy_index', 'ti', '(n.nid = %alias.nid)');
        $query->leftJoin('faq_weights', 'w', 'n.nid = %alias.nid AND %alias.tid = :category', [':category' => $category]);
        $query->condition('ti.tid', $category);
        $query->orderBy('effective_weight', 'ASC')
          ->orderBy('d.sticky', 'DESC')
          ->orderBy('d.created', $default_sorting == 'DESC' ? 'DESC' : 'ASC');
      }

      $options = $query->execute()->fetchAll();

      $form['weight']['faq_category'] = [
        '#type' => 'value',
        '#value' => $category,
      ];

      // Show table ordering form.
      $form['order_no_cats']['#tree'] = TRUE;
      $form['order_no_cats']['#theme'] = 'faq_draggable_question_order_table';

      foreach ($options as $i => $record) {
        $form['order_no_cats'][$i]['nid'] = [
          '#type' => 'hidden',
          '#value' => $record->nid,
        ];
        $form['order_no_cats'][$i]['title'] = ['#markup' => Html::escape($record->title)];
        $form['order_no_cats'][$i]['sort'] = [
          '#type' => 'weight',
          '#delta' => count($options),
          '#default_value' => $i,
        ];
      }

      $form['actions']['#type'] = 'actions';
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Save order'),
        '#button_type' => 'primary',
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $order_no_cats = $form_state->getValue('order_no_cats');
    if ($form_state->getValue('op')->__toString() == $this->t('Save order') && !empty($order_no_cats)) {
      foreach ($order_no_cats as $faq) {
        $nid = $faq['nid'];
        $index = $faq['sort'];
        Database::getConnection()->merge('faq_weights')
          ->fields([
            'weight' => $index,
          ])
          ->keys([
            'tid' => $form_state->getValue('faq_category'),
            'nid' => $nid,
          ])
          ->execute();
      }

      parent::submitForm($form, $form_state);
    }
  }

  /**
   * Rebuilds the form on the FAQ order 'Choose category' search submit.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function orderSettingsChooseCatSubmit(array &$form, FormStateInterface $form_state): void {
    $form_state->setRebuild(TRUE);
  }

}
