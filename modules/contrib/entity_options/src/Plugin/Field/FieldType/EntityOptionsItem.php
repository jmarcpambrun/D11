<?php

namespace Drupal\entity_options\Plugin\Field\FieldType;

use Drupal\Core\Field\Plugin\Field\FieldType\MapItem;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\TypedData\Exception\ReadOnlyException;

/**
 * Defines the 'map' entity field type.
 *
 * @FieldType(
 *   id = "entity_options_map",
 *   label = @Translation("Entity Options"),
 *   description = @Translation("Node options, stored a serialized array of values."),
 *   no_ui = TRUE,
 *   list_class = "\Drupal\entity_options\EntityOptionsItemList",
 *   default_widget = "entity_options"
 * )
 */
class EntityOptionsItem extends MapItem {

  use LoggerChannelTrait;

  /**
   * Whether the value has been calculated.
   *
   * @var bool
   */
  protected $isCalculated = FALSE;

  /**
   * {@inheritdoc}
   */
  public function __get($name) {
    $this->ensureCalculated();
    return parent::__get($name);
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $this->ensureCalculated();
    return parent::isEmpty();
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $this->ensureCalculated();
    return parent::getValue();
  }

  /**
   * Calculates the value of the field and sets it.
   */
  protected function ensureCalculated() {
    if (!$this->isCalculated) {
      $entity = $this->getEntity();

      // When entity form is being saved - values are wrapped into a container,
      // otherwise the entity is being accessed in a different context, in
      // which case we load values from the storage.
      $computed_value = \Drupal::service('plugin.manager.entity_options')
        ->computeValue($entity, $this->values['entity_options'] ?? NULL);
      try {
        $this->setValue($computed_value);
      }
      catch (ReadOnlyException $e) {
        $this->getLogger('entity_options')->warning($e->getMessage());
      }
      $this->isCalculated = TRUE;
    }
  }

}
