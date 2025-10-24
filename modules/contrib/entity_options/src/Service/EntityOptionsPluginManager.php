<?php

namespace Drupal\entity_options\Service;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactory;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\entity_options\Annotation\EntityOption;
use Drupal\entity_options\Plugin\EntityOptionInterface;

/**
 * Manages text processing EntityOption plugins.
 *
 * @see hook_entity_options_info_alter()
 * @see \Drupal\entity_options\Annotation\EntityOption
 * @see \Drupal\entity_options\Plugin\EntityOptionInterface
 * @see \Drupal\entity_options\Plugin\EntityOptionBase
 * @see plugin_api
 */
class EntityOptionsPluginManager extends DefaultPluginManager {

  use LoggerChannelTrait;

  /**
   * KeyValue store.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  protected $keyValue;

  /**
   * Constructs a FilterPluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Drupal\Core\KeyValueStore\KeyValueFactory $key_value_factory
   *   KeyValue store factory.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, KeyValueFactory $key_value_factory) {
    parent::__construct(
      'Plugin/EntityOption',
      $namespaces,
      $module_handler,
      EntityOptionInterface::class,
      EntityOption::class
    );
    $this->alterInfo('entity_options_info');
    $this->setCacheBackend($cache_backend, 'entity_options_plugins');

    $this->keyValue = $key_value_factory;
  }

  /**
   * Get all options for entity type.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $type
   *   Entity type configuration.
   *
   * @return \Drupal\entity_options\Annotation\EntityOption[]
   *   An array of EntityOption.
   */
  public function getTypeOptions(ConfigEntityInterface $type): array {
    $options = [];
    foreach (array_keys($this->getDefinitions()) as $plugin_id) {
      try {
        $options[$plugin_id] = $this->createInstance($plugin_id, $type->getThirdPartySetting('entity_options', $plugin_id) ?? []);
      }
      catch (PluginException $exception) {
        $this->getLogger('entity_options')->error($exception->getMessage());
      }
    }
    return $options;
  }

  /**
   * Get entity type option.
   *
   * @param string $plugin_id
   *   Option ID to get.
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $type
   *   Entity type configuration.
   *
   * @return \Drupal\entity_options\Annotation\EntityOption|null
   *   EntityOption for entity type, or NULL
   */
  public function getTypeOption(string $plugin_id, ConfigEntityInterface $type) {
    $options = $this->getTypeOptions($type);
    return $options[$plugin_id] ?? NULL;
  }

  /**
   * Computes entity options value.
   *
   * Applies passed in custom values on top of defaults, considering
   * allowed overrides.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity object.
   * @param array $value
   *   Any custom value to apply.
   *
   * @return array
   *   Computed value.
   */
  public function computeValue(EntityInterface $entity, array $value = NULL): array {
    if ($entity->getEntityType()->hasKey('bundle')) {
      $type = $entity->get($entity->getEntityType()->getKey('bundle'))->entity;
    }
    else {
      $type = $entity;
    }
    if ($entity->isNew()) {
      $value = [];
    }
    elseif (NULL === $value) {
      $collection = $this->keyValue->get($this->getCollection($entity));
      $value = $collection->get(self::getEntityOptionsStoreKey($entity->id())) ?? [];
    }
    return array_map(
      static function (EntityOptionInterface $option) use ($value) {
        // NODE: Flags and parametric options are treated differently.
        // Disabled options which don't allow overrides should not be added.
        if (!$option->getStatus() && !$option->allowsOverrides()) {
          return $option->isFlag() ? FALSE : NULL;
        }

        $default_value = $option->isFlag() ? $option->getStatus() : $option->getConfiguration();

        // Return option status/config for when overrides are not allowed,
        // not enabled, or entity value was never set.
        if (!$option->allowsOverrides() || !$option->getOverride() || !isset($value[$option->getPluginId()])) {
          return $default_value;
        }

        $entity_value = $value[$option->getPluginId()];

        // Flag option are simple boolean values.
        if ($option->isFlag()) {
          // Make sure value types are matching.
          $is_bool = is_bool($entity_value) || ($entity_value == 0 || $entity_value == 1);
          return $is_bool ? (bool) $entity_value : $default_value;
        }

        // If types of the default and the entity value do not match, then
        // return default (assuming default is always the source of truth).
        // This can be a result of option class changes, as example.
        if (gettype($default_value) !== gettype($entity_value)) {
          return $default_value;
        }
        // Ultimately, apply entity value on the top of defaults.
        // Array values.
        if (is_array($default_value)) {
          return array_replace_recursive($default_value, $entity_value);
        }
        // Scalar values.
        return $entity_value;
      },
      $this->getTypeOptions($type)
    );
  }

  /**
   * Creates or updated node option values for the given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity for which to update node options value.
   */
  public function updateEntityOptions(EntityInterface $entity) {
    if (!self::hasEntityOptionsField($entity)) {
      return NULL;
    }
    $options = $entity->get('entity_options')->first()->getValue();
    $collection = $this->keyValue->get($this->getCollection($entity));
    $collection->set(self::getEntityOptionsStoreKey($entity->id()), $options);
  }

  /**
   * Purges node option values for entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity for which to purge node options.
   */
  public function purgeEntityOptions(EntityInterface $entity): void {
    if (self::hasEntityOptionsField($entity)) {
      $this->keyValue->get($this->getCollection($entity))->delete(self::getEntityOptionsStoreKey($entity->id()));
    }
  }

  /**
   * Returns collection name that should be used for the given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity for which to return collection name.
   *
   * @return string
   *   Collection name.
   */
  protected function getCollection(EntityInterface $entity): string {
    $collection = "entity_options." . $entity->getEntityTypeId();
    if ($entity->getEntityType()->hasKey('bundle')) {
      $collection .= "." . $entity->get($entity->getEntityType()->getKey('bundle'))->entity->id();
    }
    return $collection;
  }

  /**
   * Gets the key-value store entry key for 'entity_options.*' collections.
   *
   * The code is inspired by
   * \Drupal\Core\Cache\DatabaseBackend::normalizeCid().
   *
   * @param int|string $entity_id
   *   The entity id for which to compute the key.
   *
   * @return int|string
   *   The key used to store the value in the key-value store.
   *
   * @see \Drupal\Core\Cache\DatabaseBackend::normalizeCid()
   */
  public static function getEntityOptionsStoreKey($entity_id) {
    $entity_id_is_ascii = mb_check_encoding($entity_id, 'ASCII');
    if ($entity_id_is_ascii && strlen($entity_id) <= 128) {
      // The original entity ID, if it's an ASCII of 128 characters or less.
      return $entity_id;
    }
    return Crypt::hashBase64($entity_id);
  }

  /**
   * Verifies if current entity has node options field.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity to check.
   *
   * @return bool
   *   TRUE if entity has node options field, FALSE otherwise.
   */
  public static function hasEntityOptionsField(EntityInterface $entity): bool {
    return $entity instanceof ContentEntityInterface &&
      $entity->hasField('entity_options') &&
      $entity->getFieldDefinition('entity_options')?->getType() === 'entity_options_map';
  }

}
