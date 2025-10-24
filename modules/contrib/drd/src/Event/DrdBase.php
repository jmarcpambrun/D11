<?php

namespace Drupal\drd\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\drd\Entity\BaseInterface as RemoteEntityInterface;
use Drupal\drd\Plugin\Action\BaseInterface;

/**
 * Abstract event with common code for start and finish action events in DRD.
 *
 * @package Drupal\drd\Event
 */
abstract class DrdBase extends Event {

  /**
   * The action plugin.
   *
   * @var \Drupal\drd\Plugin\Action\BaseInterface
   */
  protected BaseInterface $action;

  /**
   * The optional entity.
   *
   * @var \Drupal\drd\Entity\BaseInterface|null
   */
  protected ?RemoteEntityInterface $entity = NULL;

  /**
   * Constructs a DRD event.
   *
   * @param \Drupal\drd\Plugin\Action\BaseInterface $action
   *   The action plugin.
   * @param \Drupal\drd\Entity\BaseInterface|null $entity
   *   The optional entity.
   */
  public function __construct(BaseInterface $action, ?RemoteEntityInterface $entity = NULL) {
    $this->action = $action;
    $this->entity = $entity;
  }

  /**
   * Returns the action plugin.
   *
   * @return \Drupal\drd\Plugin\Action\BaseInterface
   *   The action plugin.
   */
  public function getAction(): BaseInterface {
    return $this->action;
  }

  /**
   * Returns the optional entity.
   *
   * @return \Drupal\drd\Entity\BaseInterface|null
   *   The optional entity.
   */
  public function getEntity(): ?RemoteEntityInterface {
    return $this->entity;
  }

}
