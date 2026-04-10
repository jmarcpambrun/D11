<?php

namespace Drupal\drd;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Action\ActionManager as CoreActionManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\drd\Entity\BaseInterface as RemoteEntityInterface;
use Drupal\drd\Event\DrdActionFinish;
use Drupal\drd\Event\DrdActionStart;
use Drupal\drd\Event\DrdEvents;
use Drupal\drd\Plugin\Action\BaseEntityInterface;
use Drupal\drd\Plugin\Action\BaseEntityRemote;
use Drupal\drd\Plugin\Action\BaseGlobalInterface;
use Drupal\drd\Plugin\Action\BaseInterface;
use Drupal\system\ActionConfigEntityInterface;
use Drupal\taxonomy\Plugin\views\wizard\TaxonomyTerm;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Action manager for DRD actions.
 */
class ActionManager extends CoreActionManager implements ActionManagerInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * An event dispatcher instance to use for ECA-related events.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, EntityTypeManagerInterface $entity_type_manager, EventDispatcherInterface $event_dispatcher) {
    parent::__construct($namespaces, $cache_backend, $module_handler);
    $this->setCacheBackend($cache_backend, 'drd_action_info');
    $this->entityTypeManager = $entity_type_manager;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public function instance($id): bool|BaseInterface|BaseEntityRemote {
    try {
      /** @var \Drupal\drd\Plugin\Action\BaseEntityRemote $action */
      $action = $this->createInstance($id);
    }
    catch (PluginException) {
      return FALSE;
    }
    if (!($action instanceof BaseInterface)) {
      return FALSE;
    }
    return $action;
  }

  /**
   * {@inheritdoc}
   */
  public function response($id, RemoteEntityInterface $remote, array $arguments = []): bool|array|string {
    $action = $this->instance($id);
    if ($action instanceof BaseInterface) {
      foreach ($arguments as $key => $value) {
        $action->setActionArgument($key, $value);
      }
      return $this->executeAction($action, $remote);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getActionsByTerm(TaxonomyTerm|string $term): array {
    $actions = [];
    $terms = [];
    try {
      $terms = is_string($term) ?
        $this->entityTypeManager->getStorage('taxonomy_term')
          ->loadByProperties(['name' => $term]) :
        [$term];
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
    }
    if ($terms) {
      $loadedTerm = reset($terms);

      try {
        $actionStorage = $this->entityTypeManager->getStorage('action');
        foreach ($actionStorage->loadMultiple() as $action) {
          if ($action instanceof ActionConfigEntityInterface) {
            $plugin = $action->getPlugin();
            if (($plugin instanceof BaseInterface) && $plugin->hasTerm($loadedTerm) && $plugin->access(NULL)) {
              $actions[] = $plugin;
            }
          }
        }
      }
      catch (InvalidPluginDefinitionException | PluginNotFoundException) {
        // @todo Log this exception.
      }
    }
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function executeAction(BaseInterface $action, ?RemoteEntityInterface $entity = NULL): mixed {
    $event = new DrdActionStart($action, $entity);
    $this->eventDispatcher->dispatch($event, DrdEvents::ACTION_STARTED);

    if ($action instanceof BaseEntityInterface && $entity !== NULL) {
      $result = $action->executeAction($entity);
    }
    elseif ($action instanceof BaseGlobalInterface) {
      $result = $action->executeAction();
    }
    else {
      $result = FALSE;
    }

    $event = new DrdActionFinish($action, $entity);
    $this->eventDispatcher->dispatch($event, DrdEvents::ACTION_FINISHED);

    return $result;
  }

}
