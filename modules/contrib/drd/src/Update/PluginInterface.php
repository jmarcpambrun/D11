<?php

namespace Drupal\drd\Update;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Defines the required interface for all DRD Update plugins.
 */
interface PluginInterface extends ConfigurableInterface, PluginFormInterface {

  /**
   * Set the parent form id and conditions for the config sub-form.
   *
   * @param string $parent
   *   The form parent element id.
   * @param array $condition
   *   List of visibility conditions.
   *
   * @return $this
   */
  public function setConfigFormContext(string $parent, array $condition): self;

  /**
   * Get a list of implemented hooks for extra scripts.
   *
   * @return array
   *   List of implemented script hooks.
   */
  public function scriptHooks(): array;

  /**
   * Execute a script.
   *
   * @param PluginStorageInterface $storage
   *   The storage plugin.
   * @param string $hook
   *   The script hook.
   *
   * @return $this
   */
  public function executeScript(PluginStorageInterface $storage, string $hook): self;

  /**
   * Cleanup callback.
   *
   * Called before saving the working directory back to the source in reverse
   * order of all the used plugins.
   *
   * @param PluginStorageInterface $storage
   *   The storage plugin.
   *
   * @return $this
   */
  public function cleanup(PluginStorageInterface $storage): self;

}
