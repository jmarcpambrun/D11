<?php

namespace Drupal\drd\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\drd\ContextProvider\RouteContext;
use Drupal\drd\Entity\BaseInterface;
use Drupal\drd\QueueManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Abstract class for DRD blocks.
 */
abstract class Base extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Route context of current request.
   *
   * @var \Drupal\drd\ContextProvider\RouteContext|null
   */
  protected ?RouteContext $drdContext = NULL;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected FormBuilderInterface $formBuilder;

  /**
   * The link generator service.
   *
   * @var \Drupal\Core\Utility\LinkGeneratorInterface
   */
  protected LinkGeneratorInterface $linkGenerator;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The queue manager.
   *
   * @var \Drupal\drd\QueueManager
   */
  protected QueueManager $queueManager;

  /**
   * The Drupal state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * {@inheritdoc}
   */
  final public function __construct(array $configuration, $plugin_id, $plugin_definition, AccountInterface $current_user, Connection $database, EntityTypeManagerInterface $entity_type_manager, FormBuilderInterface $form_builder, LinkGeneratorInterface $link_generator, ModuleHandlerInterface $module_handler, QueueManager $queue_manager, StateInterface $state, ?RouteContext $context = NULL) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->drdContext = $context;
    $this->currentUser = $current_user;
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
    $this->formBuilder = $form_builder;
    $this->linkGenerator = $link_generator;
    $this->moduleHandler = $module_handler;
    $this->queueManager = $queue_manager;
    $this->state = $state;
    $this->init();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): Base {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('form_builder'),
      $container->get('link_generator'),
      $container->get('module_handler'),
      $container->get('queue.drd'),
      $container->get('state'),
      RouteContext::findDrdContext()
    );
  }

  /**
   * Optionallya initialize the instance.
   */
  protected function init(): void {
    // Can be overwritten.
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    return 0;
  }

  /**
   * Determine if the current request is within the context of a DRD entity.
   *
   * @return bool
   *   TRUE if context is within a DRD entity.
   */
  protected function isDrdContext(): bool {
    return $this->drdContext && $this->drdContext->getEntity();
  }

  /**
   * Get the entity of the current context.
   *
   * @return bool|\Drupal\drd\Entity\BaseInterface
   *   The DRD entity if within an entity context or FALSE otherwise.
   */
  protected function getEntity(): BaseInterface|bool {
    return $this->drdContext ? $this->drdContext->getEntity() : FALSE;
  }

}
