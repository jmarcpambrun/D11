<?php

namespace Drupal\phpmailer_smtp\Plugin\PhpmailerOauth2;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;

/**
 * Base class for PHPMailer Oauth2 plugins.
 */
abstract class PhpmailerOauth2PluginBase extends PluginBase implements PhpmailerOauth2PluginInterface {

  /**
   * {@inheritdoc}
   */
  public function getName() {
    $this->checkKeyDefined('name');

    return $this->pluginDefinition['name'];
  }

  /**
   * {@inheritdoc}
   */
  public function getId() {
    $this->checkKeyDefined('id');

    return $this->pluginDefinition['id'];
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthOptions() {

    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {}

  /**
   * Checks that a key is defined in the plugin definition.
   *
   * Attribute-based discovery enforces required keys at discovery time, so
   * this guard only matters for plugins still discovered via the legacy
   * annotation.
   *
   * @param string $key
   *   The plugin definition key to check.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   Thrown when the key is not defined.
   */
  protected function checkKeyDefined(string $key): void {
    if (!isset($this->pluginDefinition[$key])) {
      throw new PluginException(sprintf(
        'The "%s" plugin does not define the required "%s" key.',
        $this->getPluginId(),
        $key,
      ));
    }
  }

}
