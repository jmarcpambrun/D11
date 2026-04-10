<?php

namespace Drupal\drd\Plugin\Action;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\drd\ActionManagerInterface;
use Drupal\drd\Entity\BaseInterface as RemoteEntityInterface;
use Drupal\drd\HttpRequest;
use Drupal\drd\Logging;
use Drupal\drd\QueueManager;
use Drupal\drd\SelectEntitiesInterface;
use Drupal\drd\UpdateProcessor;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for DRD Action plugins.
 */
abstract class Base extends ActionBase implements BaseInterface, ConfigurableInterface, PluginFormInterface, ContainerFactoryPluginInterface {

  /**
   * Action arguments.
   *
   * @var array
   */
  protected array $arguments = [];

  /**
   * Action output.
   *
   * @var string[]
   */
  protected array $output = [];

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * The service to select DRD entities.
   *
   * @var \Drupal\drd\SelectEntitiesInterface
   */
  protected SelectEntitiesInterface $entities;

  /**
   * The http request services.
   *
   * @var \Drupal\drd\HttpRequest
   */
  protected HttpRequest $httpRequest;

  /**
   * The logging channel.
   *
   * @var \Drupal\drd\Logging
   */
  protected Logging $logging;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The DRD action manager.
   *
   * @var \Drupal\drd\ActionManagerInterface
   */
  protected ActionManagerInterface $actionManager;

  /**
   * The queue manager.
   *
   * @var \Drupal\drd\QueueManager
   */
  protected QueueManager $queueManager;

  /**
   * The DRD update processor.
   *
   * @var \Drupal\drd\UpdateProcessor
   */
  protected UpdateProcessor $updateProcessor;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  final public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, AccountInterface $current_user, SelectEntitiesInterface $entities, HttpRequest $http_request, Logging $logging, EntityTypeManagerInterface $entityTypeManager, FileSystemInterface $file_system, ModuleHandlerInterface $module_handler, ActionManagerInterface $action_manager, QueueManager $queue_manager, UpdateProcessor $update_processor) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->currentUser = $current_user;
    $this->entities = $entities;
    $this->httpRequest = $http_request;
    $this->logging = $logging;
    $this->entityTypeManager = $entityTypeManager;
    $this->fileSystem = $file_system;
    $this->moduleHandler = $module_handler;
    $this->actionManager = $action_manager;
    $this->queueManager = $queue_manager;
    $this->updateProcessor = $update_processor;
    $this->setDefaultArguments();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): Base {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('current_user'),
      $container->get('drd.entities.select'),
      $container->get('drd.http_request'),
      $container->get('drd.logging'),
      $container->get('entity_type.manager'),
      $container->get('file_system'),
      $container->get('module_handler'),
      $container->get('plugin.manager.drd_action'),
      $container->get('queue.drd'),
      $container->get('update.processor.drd')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $terms = [];
    foreach ($this->configuration['terms'] ?? [] as $id) {
      $terms[] = Term::load($id);
    }
    $form['terms'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'taxonomy_term',
      '#title' => 'Terms',
      '#default_value' => $terms,
      '#tags' => TRUE,
      '#autocreate' => [
        'bundle' => 'tags',
        'uid' => $this->currentUser->id(),
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    // Nothing to do so far.
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['terms'] = [];
    foreach ($form_state->getValue('terms') as $item) {
      $item = reset($item);
      if ($item instanceof EntityInterface) {
        if ($item->isNew()) {
          /* @noinspection PhpUnhandledExceptionInspection */
          $item->save();
        }
        $this->configuration['terms'][] = $item->id();
      }
      else {
        $this->configuration['terms'][] = $item;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = NestedArray::mergeDeep(
      $this->defaultConfiguration(),
      $configuration
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'terms' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    if (($this instanceof BaseEntityInterface) && func_num_args() && ($entity = func_get_arg(0)) && $entity instanceof RemoteEntityInterface) {
      $this->executeAction($entity);
    }
  }

  /**
   * Allows an action to set default arguments.
   */
  protected function setDefaultArguments(): void {}

  /**
   * {@inheritdoc}
   */
  public function restrictAccess(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function canBeQueued(): bool {
    return TRUE;
  }

  /**
   * Get a list of optional follow-up actions.
   *
   * @return array|string|bool
   *   Return the action key, or a list of action keys, that should follow this
   *   action or FALSE, if no follow-up action required.
   */
  protected function getFollowUpAction(): array|string|bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE): bool|AccessResultInterface {
    if (PHP_SAPI === 'cli' || @ignore_user_abort()) {
      // We are either running via CLI (Drush or Console) or it's a cron run.
      return $return_as_object ? AccessResult::allowed() : TRUE;
    }
    if (!$account) {
      $account = $this->currentUser;
    }
    if ($account->hasPermission($this->getPluginId())) {
      return $return_as_object ? AccessResult::allowed() : TRUE;
    }
    return $return_as_object ? AccessResult::forbidden() : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  final public function setActionArgument(string $key, mixed $value): BaseInterface {
    $this->arguments[$key] = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  final public function setArguments(array $arguments): BaseInterface {
    foreach ($arguments as $key => $value) {
      $this->arguments[$key] = $value;
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  final public function getArguments(): array {
    return $this->arguments;
  }

  /**
   * Reset action such that another request can be executed.
   */
  protected function reset(): void {
    $this->output = [];
  }

  /**
   * Add another part to the action output.
   *
   * @param string $output
   *   The new part for the output.
   */
  final protected function setOutput(string $output): void {
    $this->output[] = $output;
  }

  /**
   * {@inheritdoc}
   */
  final public function getOutput(): array {
    return $this->output;
  }

  /**
   * Logging fort actions, forwarding to the DRD logging service.
   *
   * @param string $severity
   *   The message severity.
   * @param string $message
   *   The log message.
   * @param array $args
   *   The log message arguments.
   */
  protected function log(string $severity, string $message, array $args = []): void {
    $args['@plugin_id'] = $this->pluginId;
    $this->logging->log($severity, $message, $args);
  }

  /**
   * {@inheritdoc}
   */
  public function hasTerm(Term $term): bool {
    return in_array($term->id(), $this->configuration['terms'], TRUE);
  }

}
