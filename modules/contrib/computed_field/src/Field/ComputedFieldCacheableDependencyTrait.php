<?php

declare(strict_types=1);

namespace Drupal\computed_field\Field;

use Drupal\Core\Cache\CacheableMetadata;

/**
 * Trait for our field classes.
 *
 * This is needed because we need to supply different field classes for
 * different field types, which inherit from different classes, but whose
 * implementation of CacheableDependencyInterface does not need to differ.
 */
trait ComputedFieldCacheableDependencyTrait {

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    $cache_metadata = $this->getCacheability();
    return $cache_metadata ? $cache_metadata->getCacheContexts() : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    $cache_metadata = $this->getCacheability();
    return $cache_metadata ? $cache_metadata->getCacheTags() : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    $cache_metadata = $this->getCacheability();
    return $cache_metadata ? $cache_metadata->getCacheMaxAge() : -1;
  }

  /**
   * The cacheability of this field item, as defined by the field value plugin.
   *
   * @return \Drupal\Core\Cache\CacheableMetadata|null
   */
  protected function getCacheability(): ?CacheableMetadata {
    /** @var \Drupal\computed_field\Field\ComputedFieldDefinitionWithValuePluginInterface $field_definition */
    $field_definition = $this->getFieldDefinition();
    $computed_field_plugin = $field_definition->getFieldValuePlugin();
    $host_entity = $this->getParent()->getValue();

    // If the plugin uses a lazy builder for its output, we do not need/want
    // to bubble up the cache metadata to the field level.
    if ($computed_field_plugin->useLazyBuilder($host_entity, $field_definition)) {
      return NULL;
    }

    return $computed_field_plugin->getCacheability($host_entity, $field_definition);
  }

}
