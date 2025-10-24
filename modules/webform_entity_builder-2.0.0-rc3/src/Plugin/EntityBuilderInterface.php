<?php

namespace Drupal\webform_entity_builder\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Defines an interface for Entity builder plugins.
 */
interface EntityBuilderInterface extends PluginInspectionInterface {
  /**
   * Compile the values to create an entity given the data (usually from the webform).
   *
   * @param mixed[] $data
   *
   * @return mixed[]
   */
  public function build(array $data);

  /**
   * Perform any finalisation needed (such as attaching files to the entity)
   *
   * @param EntityInterface $entity
   */
  public function finalise(EntityInterface $entity);

}
