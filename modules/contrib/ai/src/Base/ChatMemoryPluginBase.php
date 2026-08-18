<?php

namespace Drupal\ai\Base;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\Plugin\ChatMemory\ChatMemoryInterface;
use Drupal\Core\Plugin\ConfigurablePluginBase;

/**
 * Base class for ChatMemory plugins.
 *
 * This base class provides configuration and form boilerplate for ChatMemory
 * plugins, plus a default appendMessages() implementation based on
 * loadMessages() and saveMessages().
 */
abstract class ChatMemoryPluginBase extends ConfigurablePluginBase implements ChatMemoryInterface {

  /**
   * {@inheritdoc}
   */
  public function appendMessages(string $thread_id, array $messages): void {
    foreach ($messages as $message) {
      if (!$message instanceof ChatMessage) {
        throw new \InvalidArgumentException('Messages must be instances of ChatMessage.');
      }
    }
    $this->saveMessages($thread_id, array_merge($this->loadMessages($thread_id), $messages));
  }

  /**
   * {@inheritdoc}
   */
  public function clearMessages(string $thread_id): void {
    $this->saveMessages($thread_id, []);
  }

  /**
   * {@inheritdoc}
   */
  public function hasPersistentThread(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->setConfiguration($form_state->getValues());
  }

}
