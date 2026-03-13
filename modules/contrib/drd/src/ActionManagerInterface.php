<?php

namespace Drupal\drd;

use Drupal\drd\Entity\BaseInterface as RemoteEntityInterface;
use Drupal\drd\Plugin\Action\BaseInterface;
use Drupal\taxonomy\Plugin\views\wizard\TaxonomyTerm;

/**
 * Interface for the DRD action manager.
 */
interface ActionManagerInterface {

  /**
   * Create an action instance for the given action ID.
   *
   * @param string $id
   *   The action id.
   *
   * @return \Drupal\drd\Plugin\Action\BaseInterface|bool
   *   Returns the action instance or FALSE if no action with the given id was
   *   found or if it had the wrong type.
   */
  public function instance(string $id): bool|BaseInterface;

  /**
   * Create and action instance and execute it on a given remote entity.
   *
   * @param string $id
   *   The action id.
   * @param \Drupal\drd\Entity\BaseInterface $remote
   *   The remote DRD entity.
   * @param array $arguments
   *   The action arguments.
   *
   * @return array|bool|string
   *   The json decoded response from the remote entity or FALSE, if execution
   *   failed.
   */
  public function response(string $id, RemoteEntityInterface $remote, array $arguments = []): bool|array|string;

  /**
   * Returns all actions tagged wuth a given term.
   *
   * @param string|TaxonomyTerm $term
   *   The term or a term name.
   *
   * @return \Drupal\drd\Plugin\Action\BaseInterface[]
   *   All action plugins depending on mode and/or term.
   */
  public function getActionsByTerm(TaxonomyTerm|string $term): array;

  /**
   * Executes the action plugin.
   *
   * @param \Drupal\drd\Plugin\Action\BaseInterface $action
   *   The action plugin.
   * @param \Drupal\drd\Entity\BaseInterface|null $entity
   *   The optional entity.
   *
   * @return mixed
   *   If available, the response from the action.
   */
  public function executeAction(BaseInterface $action, ?RemoteEntityInterface $entity = NULL): mixed;

}
