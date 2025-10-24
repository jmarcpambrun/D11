<?php

namespace Drupal\drd\Drush\Commands;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\drd\ActionManagerInterface;
use Drupal\drd\Logging;
use Drupal\drd\Plugin\Action\BaseEntityInterface;
use Drupal\drd\Plugin\Action\BaseGlobalInterface;
use Drupal\drd\Plugin\Action\BaseInterface;
use Drupal\drd\QueueManager;

/**
 * Trait for DrdCommands to provide shared properties and method.
 */
trait DrdCommandsTrait {

  /**
   * DRD action which will be executed.
   *
   * @var \Drupal\drd\Plugin\Action\BaseInterface|bool
   */
  protected mixed $action;

  /**
   * ID of the action to be executed.
   *
   * @var string
   */
  protected string $actionKey;

  /**
   * Options from the command line.
   *
   * @var array
   */
  protected array $options = [];

  /**
   * List of entities for which the action will be executed.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
   */
  protected array $entities;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The logging channel.
   *
   * @var \Drupal\drd\Logging
   */
  protected Logging $logging;

  /**
   * The queue manager.
   *
   * @var \Drupal\drd\QueueManager
   */
  protected QueueManager $queueManager;

  /**
   * The DRD action manager.
   *
   * @var \Drupal\drd\ActionManagerInterface
   */
  protected ActionManagerInterface $actionManager;

  /**
   * Prepare services, the action and their arguments.
   *
   * @param array $arguments
   *   Associated array with arguments.
   * @param array $options
   *   List of option keys.
   *
   * @return $this
   */
  protected function prepare(array $arguments = [], array $options = []): self {
    $this->logging->setIO($this->io());
    $this->action = $this->actionManager->instance($this->actionKey);
    if (!($this->action instanceof BaseInterface)) {
      return $this;
    }
    try {
      /** @var \Drupal\Core\Session\AccountInterface $account */
      $account = $this->entityTypeManager->getStorage('user')->load(1);
      $this->currentUser->setAccount($account);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->logging->log('error', $e->getMessage());
    }

    foreach ($arguments as $key => $value) {
      $this->action->setActionArgument($key, $value);
    }

    foreach ($options as $key) {
      if (isset($this->options[$key])) {
        $this->action->setActionArgument($key, $this->options[$key]);
      }
    }

    return $this;
  }

  /**
   * Callback to execute the prepared action.
   *
   * @return false|mixed
   *   The execution result of FALSE otherwise.
   */
  protected function execute(): mixed {
    if (empty($this->action)) {
      return FALSE;
    }
    if ($this->action instanceof BaseGlobalInterface) {
      $this->entities[] = FALSE;
    }
    elseif (empty($this->entities)) {
      return FALSE;
    }

    $result = FALSE;
    $ok = $failure = 0;
    $this->io()->title('Executing ' . $this->action->getPluginDefinition()['label']);
    foreach ($this->entities as $entity) {
      if ($entity) {
        /** @var \Drupal\drd\Entity\BaseInterface $entity */
        /** @var \Drupal\drd\Plugin\Action\BaseEntityInterface $action */
        $action = $this->action;
        $this->io()->section('- on id ' . $entity->id() . ': ' . $entity->getName());
        $result = $this->actionManager->executeAction($action, $entity);
      }
      elseif ($this->action instanceof BaseEntityInterface || $this->action instanceof BaseGlobalInterface) {
        $result = $this->actionManager->executeAction($this->action);
      }
      if ($result !== FALSE) {
        $this->io()->success('  ok!');
        $ok++;
      }
      else {
        $this->io()->error('  failure!');
        $failure++;
      }

      $output = $this->action->getOutput();
      if ($output) {
        foreach ($output as $value) {
          $this->io()->write('  ' . $value, TRUE);
        }
      }
    }

    $this->queueManager->processAll();

    if (empty($failure)) {
      $this->io()->success('Completed!');
    }
    elseif (empty($ok)) {
      $this->io()->error('Completed!');
    }
    else {
      $this->io()->warning('Completed!');
    }
    return $result;
  }

}
