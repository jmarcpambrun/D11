<?php

namespace Drupal\drd\Crypt\Method;

use Drupal\Core\Form\FormStateInterface;
use Drupal\drd\Crypt\BaseMethod;

/**
 * Provides security over TLS without additional encryption.
 *
 * @ingroup drd
 */
class Tls extends BaseMethod {

  /**
   * {@inheritdoc}
   */
  public function authBeforeDecrypt(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function requiresPassword(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return 'TLS';
  }

  /**
   * {@inheritdoc}
   */
  public function getCipher(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getPassword(): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool {
    if ($this->moduleHandler->moduleExists('drd')) {
      // We are on the dashboard and not remote, so here we do support TLS.
      return TRUE;
    }
    // @todo properly find out if the remote site is running on TLD.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getCipherMethods(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array &$form, array $condition): void {
    $form['info'] = [
      '#type' => 'item',
      '#markup' => t('No settings required.'),
      '#states' => [
        'required' => $condition,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsFormValues(FormStateInterface $form_state): array {
    return $this->getSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function getIv(): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function encrypt(array $args): string {
    return serialize($args);
  }

  /**
   * {@inheritdoc}
   */
  public function decrypt(string $body, string $iv): mixed {
    /* @noinspection UnserializeExploitsInspection */
    return unserialize($body);
  }

}
