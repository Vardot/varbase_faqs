<?php

namespace Drupal\faq\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\faq\FaqHelper;
use Drupal\node\NodeInterface;

/**
 * Hook implementations for the FAQ module.
 */
class FaqHooks {

  use StringTranslationTrait;

  /**
   * Constructs a FaqHooks object.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected AccountInterface $currentUser,
    protected RendererInterface $renderer,
    protected ConfigFactoryInterface $configFactory,
    protected RouteMatchInterface $routeMatch,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    $output = '';
    switch ($route_name) {
      case 'help.page.faq':
        $output .= '<p>' . $this->t("This module allows users with the 'administer faq' permission to create question and answer pairs which will be displayed on the faq page.  The faq page is automatically generated from the FAQ nodes configured and the layout of this page can be modified on the settings page.  Users will need the 'view faq page' permission in order to view the faq page.") . '</p>' .
          '<p>' . $this->t("To create a question and answer, the user must create a 'FAQ' node (Create content >> FAQ).  This screen allows the user to edit the question and answer text.  If the 'Taxonomy' module is enabled and there are some terms configured for the FAQ node type, it will also be possible to put the questions into different categories when editing.") . '</p>' .
          '<p>' . $this->t("The 'Frequently Asked Questions' settings configuration screen will allow users with 'administer faq' permissions to specify different layouts of the questions and answers.") . '</p>' .
          '<p>' . $this->t("All users with 'view faq page' permissions will be able to view the generated FAQ page.") . '</p>';
        return $output;
    }
  }

  /**
   * Implements hook_node_access().
   */
  #[Hook('node_access')]
  public function nodeAccess(NodeInterface $node, $op, AccountInterface $account) {
    // Ignore non-FAQ node.
    if ($node->getType() !== 'faq') {
      return AccessResult::neutral();
    }

    if ($op == 'view') {
      return AccessResult::neutral();
    }
    elseif ($op == 'create' || $op == 'update' || $op == 'delete') {
      if ($this->currentUser->hasPermission('administer faq')) {
        return AccessResult::allowed();
      }
    }

    return AccessResult::neutral();
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'faq_draggable_question_order_table' => [
        'template' => 'faq-draggable-question-order-table',
        'render element' => 'form',
      ],
      'faq_questions_top' => [
        'file' => '/includes/faq.questions_top.inc',
        'template' => 'faq-questions-top',
        'variables' => ['data' => NULL],
      ],
      'faq_category_questions_top' => [
        'file' => '/includes/faq.questions_top.inc',
        'template' => 'faq-category-questions-top',
        'variables' => ['data' => NULL, 'display_header' => 0, 'category_display' => NULL, 'term' => NULL, 'class' => NULL, 'parent_term' => NULL],
      ],
      'faq_category_questions_top_answers' => [
        'file' => '/includes/faq.questions_top.inc',
        'template' => 'faq-category-questions-top-answers',
        'variables' => ['data' => NULL, 'display_header' => 0, 'category_display' => NULL, 'term' => NULL, 'class' => NULL, 'parent_term' => NULL],
      ],
      'faq_hide_answer' => [
        'file' => '/includes/faq.hide_answer.inc',
        'template' => 'faq-hide-answer',
        'variables' => ['data' => NULL],
      ],
      'faq_category_hide_answer' => [
        'file' => '/includes/faq.hide_answer.inc',
        'template' => 'faq-category-hide-answer',
        'variables' => ['data' => NULL, 'display_header' => 0, 'category_display' => NULL, 'term' => NULL, 'class' => NULL, 'parent_term' => NULL],
      ],
      'faq_questions_inline' => [
        'file' => '/includes/faq.questions_inline.inc',
        'template' => 'faq-questions-inline',
        'variables' => ['data' => NULL],
      ],
      'faq_category_questions_inline' => [
        'file' => '/includes/faq.questions_inline.inc',
        'template' => 'faq-category-questions-inline',
        'variables' => ['data' => NULL, 'display_header' => 0, 'category_display' => NULL, 'term' => NULL, 'class' => NULL, 'parent_term' => NULL],
      ],
      'faq_new_page' => [
        'file' => '/includes/faq.new_page.inc',
        'template' => 'faq-new-page',
        'variables' => ['data' => NULL],
      ],
      'faq_category_new_page' => [
        'file' => '/includes/faq.new_page.inc',
        'template' => 'faq-category-new-page',
        'variables' => ['data' => NULL, 'display_header' => 0, 'category_display' => NULL, 'term' => NULL, 'class' => NULL, 'parent_term' => NULL],
      ],
      'faq_page' => [
        'variables' => ['content' => '', 'answers' => '', 'description' => NULL],
        'template' => 'faq-page',
      ],
    ];
  }

  /**
   * Implements hook_preprocess_HOOK() for the question ordering table.
   *
   * Theme preprocess for the question ordering drag and drop table.
   */
  #[Hook('preprocess_faq_draggable_question_order_table')]
  public function preprocessFaqDraggableQuestionOrderTable(array &$variables): void {
    $form = $variables['form'];
    $header = ['', $this->t('Question'), '', $this->t('Sort')];
    $rows = [];
    foreach (Element::children($form) as $key) {
      // Add class to group weight fields for drag and drop.
      $form[$key]['sort']['#attributes']['class'] = ['sort'];
      $form[$key]['nid']['#attributes']['class'] = ['hidden-nid'];
      $row = [''];
      $row[] = $this->renderer->render($form[$key]['title']);
      $row[] = $this->renderer->render($form[$key]['nid']);
      $row[] = $this->renderer->render($form[$key]['sort']);

      $rows[] = [
        'data' => $row,
        'class' => ['draggable'],
      ];
    }

    $table = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#attributes' => [
        'id' => 'question-sort',
      ],
      // Drupal 11 removed drupal_attach_tabledrag(); declare the tabledrag
      // behaviour directly on the table render element instead.
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'sort',
        ],
      ],
    ];
    $table['#attached']['library'][] = 'core/drupal.tabledrag';

    $variables['order_table'] = $table;
  }

  /**
   * Implements hook_preprocess_HOOK() for the FAQ page.
   *
   * Theme preprocess for the FAQ page wrapper divs.
   */
  #[Hook('preprocess_faq_page')]
  public function preprocessFaqPage(array &$variables): void {
    $faq_settings = $this->configFactory->get('faq.settings');
    if ($faq_settings->get('show_expand_all')) {
      $variables['faq_expand'] = TRUE;
    }
    else {
      $variables['faq_expand'] = FALSE;
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for pages.
   *
   * Overrides breadcrumbs for FAQ pages.
   */
  #[Hook('preprocess_page')]
  public function preprocessPage(array &$variables): void {
    $faq_settings = $this->configFactory->get('faq.settings');
    $use_categories = $faq_settings->get('use_categories');

    $tid = $this->routeMatch->getRawParameter('tid');

    if (FaqHelper::searchInArgs('faq-page') && $use_categories && is_numeric($tid)) {
      $current_term = $this->entityTypeManager->getStorage('taxonomy_term')->load($tid);
      if ($current_term) {
        $breadcrumb = FaqHelper::setFaqBreadcrumb($current_term);
        if (!empty($breadcrumb)) {
          $variables['breadcrumb']['#breadcrumb'] = $breadcrumb;
        }
      }
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter() for node_faq_edit_form.
   *
   * If question_long_form is disabled, hide the 'detailed question' from node
   * editing.
   */
  #[Hook('form_node_faq_edit_form_alter')]
  public function formNodeFaqEditFormAlter(array &$form, FormStateInterface $form_state, $form_id): void {
    $faq_settings = $this->configFactory->get('faq.settings');
    if (!$faq_settings->get('question_long_form')) {
      $form['field_detailed_question']['#access'] = FALSE;
    }
  }

}
