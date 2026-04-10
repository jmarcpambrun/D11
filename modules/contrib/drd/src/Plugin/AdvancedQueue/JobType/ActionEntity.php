<?php

namespace Drupal\drd\Plugin\AdvancedQueue\JobType;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;

/**
 * Provides an AdvancedQueue JobType for DRD Entities.
 *
 * @AdvancedQueueJobType(
 *  id = "drd_action_entity",
 *  label = @Translation("DRD Entity Action"),
 * )
 */
class ActionEntity extends Action {

  /**
   * {@inheritdoc}
   */
  public function processAction(): bool|array {
    /** @var \Drupal\drd\Plugin\Action\BaseEntityInterface $action */
    $action = $this->action;
    try {
      /** @var \Drupal\drd\Entity\BaseInterface|null $entity */
      $entity = $this->entityTypeManager
        ->getStorage($this->payload['entity_type'])
        ->load($this->payload['entity_id']);
      if ($entity !== NULL) {
        return ($this->actionManager->executeAction($action, $entity) !== FALSE);
      }
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      // @todo Log this exception.
    }
    return FALSE;
  }

}
