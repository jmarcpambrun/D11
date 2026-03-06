<?php

namespace Drupal\entity\Plugin\Action;

use Drupal\Core\Action\Plugin\Action\DeleteAction as CoreDeleteAction;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;

/**
 * Redirects to an entity deletion form.
 *
 * @deprecated Use "entity:delete_action" instead.
 *
 * @Action(
 *   id = "entity_delete_action",
 *   label = @Translation("Delete entity"),
 *   deriver = "Drupal\entity\Plugin\Action\Derivative\DeleteActionDeriver",
 * )
 */
class DeleteAction extends CoreDeleteAction {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, PrivateTempStoreFactory $temp_store_factory, AccountInterface $current_user) {
    @trigger_error('\Drupal\entity\Plugin\Action\DeleteAction has been deprecated in favor of \Drupal\Core\Action\Plugin\Action\DeleteAction. Use that instead.', E_USER_DEPRECATED);
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $temp_store_factory, $current_user);
  }
}

