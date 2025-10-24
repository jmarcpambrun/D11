<?php

namespace Drupal\entity_options\Plugin;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines interface for EntityOption plugins.
 */
interface EntityOptionInterface extends PluginInspectionInterface, ConfigurableInterface {

  /**
   * Returns the administrative label for this plugin.
   *
   * @return string
   *   Entity option label.
   */
  public function getLabel();

  /**
   * Returns the administrative description for this plugin.
   *
   * @return string
   *   Entity option description.
   */
  public function getDescription();

  /**
   * Checks whether this node option allows per-node overrides.
   *
   * @return bool
   *   TRUE is per-node overrides are allowed, otherwise false.
   */
  public function allowsOverrides(): bool;

  /**
   * Builds node option configuration form.
   *
   * Invoked in node_form_alter or node_type_form_alter to allow
   * administrators to configure the option.
   *
   * @param array $form
   *   Entity or entity type form, depending on the context.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   * @param array $values
   *   Stored option values.
   *
   * @return array|null
   *   Node option form elements.
   */
  public function settingsForm(array $form, FormStateInterface $form_state, array $values): ?array;

  /**
   * Returns the status of the option.
   *
   * This method reads 'status' entry in the configuration array.
   *
   * @return bool
   *   TRUE if option is enabled, FALSE otherwise.
   */
  public function getStatus(): bool;

  /**
   * Checks where this option allows on per-node overrides.
   *
   * This method reads 'override' entry in the configuration array.
   * This method is applicable for the NodeType context.
   *
   * @return bool
   *   TRUE is node overrides are enabled, FALSE otherwise.
   */
  public function getOverride();

  /**
   * Checks whether the option is a flag or no.
   *
   * This method plays important role during option value computation.
   *
   * @return bool
   *   TRUE if the options acts like a flag, FALSE otherwise.
   */
  public function isFlag();

}
