<?php
/**
 * Created by PhpStorm.
 * User: steve
 * Date: 14/09/18
 * Time: 12:47
 */

namespace Drupal\webform_entity_builder\Plugin\EntityBuilder;

use Drupal\Core\Annotation\Translation;
use Drupal\webform_entity_builder\Annotation\EntityBuilder;
use Drupal\webform_entity_builder\Plugin\EntityBuilderBase;

/**
 * Class ExampleEntityBuilder
 *
 * (Note: Gap left below to avoid it actually being recognised.)
 *
 * @ EntityBuilder(
 *   id = "example_entity_builder",
 *   label = @Translation("Example Entity Builder"),
 *   description = @Translation("This doesn't match any actual activities so will never be used."),
 *   type = "node:article"
 * )
 *
 * @package Drupal\webform_entity_builder\Plugin\EntityBuilder
 */
class ExampleEntityBuilder extends EntityBuilderBase {

  /**
   * Build the entity-specific values before creating the entity
   *
   * @param mixed[] $data
   *
   * @return mixed[]
   */
  protected function entitySpecificFields(array $data) {
    // TODO: Implement entitySpecificFields() method.
    return [];
  }
}
