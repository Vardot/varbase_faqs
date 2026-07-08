<?php

namespace Drupal\faq\Plugin\Block;

use Drupal\Core\Url;
use Drupal\faq\FaqHelper;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a simple block.
 *
 * @Block(
 *   id = "faq_categories",
 *   admin_label = @Translation("FAQ Categories")
 * )
 */
class FaqCategoriesBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The configuration factory.
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
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The link generator service.
   *
   * @var \Drupal\Core\Utility\LinkGeneratorInterface
   */
  protected $linkGenerator;

  /**
   * Constructs a new FaqCategoriesBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link_generator
   *   The link generator service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, ModuleHandlerInterface $module_handler, EntityTypeManagerInterface $entity_type_manager, LinkGeneratorInterface $link_generator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->moduleHandler = $module_handler;
    $this->entityTypeManager = $entity_type_manager;
    $this->linkGenerator = $link_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('module_handler'),
      $container->get('entity_type.manager'),
      $container->get('link_generator')
    );
  }

  /**
   * Implements \Drupal\block\BlockBase::blockBuild().
   */
  public function build() {
    static $vocabularies, $terms;
    $items = [];

    $faq_settings = $this->configFactory->get('faq.settings');
    if (!$faq_settings->get('use_categories')) {
      return [];
    }

    if ($this->moduleHandler->moduleExists('taxonomy')) {
      if (!isset($terms)) {
        $terms = [];
        $vocabularies = Vocabulary::loadMultiple();
        $vocab_omit = array_flip($faq_settings->get('omit_vocabulary'));
        $vocabularies = array_diff_key($vocabularies, $vocab_omit);
        foreach ($vocabularies as $vocab) {
          foreach ($this->entityTypeManager->getStorage('taxonomy_term')->loadTree($vocab->id()) as $term) {
            if (FaqHelper::taxonomyTermCountNodes($term->tid)) {
              $terms[$term->name] = $term->tid;
            }
          }
        }
      }
      if (count($terms) > 0) {
        foreach ($terms as $name => $tid) {
          $items[] = $this->linkGenerator->generate($name, Url::fromUserInput('/faq-page/' . $tid));
        }
      }
    }
    return [
      '#theme' => 'item_list',
      '#items' => $items,
      '#list_type' => $faq_settings->get('category_listing'),
    ];
  }

}
