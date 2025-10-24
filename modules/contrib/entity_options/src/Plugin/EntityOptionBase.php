<?php

namespace Drupal\entity_options\Plugin;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;

/**
 * Abstract implementation of a simple flag (checkbox) options.
 *
 * Plugins aiming to provide a simple checkbox node option should implement
 * plugin definition which extends this base class. No more coding is required.
 *
 * Plugins aiming to provide a configurable node option should extend this class
 * override relevant form and settings methods.
 */
abstract class EntityOptionBase extends PluginBase implements EntityOptionInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    // Re-apply configuration on top of defaults.
    $this->configuration = array_replace_recursive($this->configuration, $this->defaultConfiguration(), $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function allowsOverrides(): bool {
    return $this->pluginDefinition['allow_overrides'];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'status' => FALSE,
      'overrides' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state, array $values): ?array {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus(): bool {
    return $this->configuration['status'] ?? FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getOverride() {
    return $this->configuration['overrides'] ?? FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration['configuration'];
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration($configuration): void {
    $this->configuration['configuration'] = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function isFlag(): bool {
    return TRUE;
  }

}
