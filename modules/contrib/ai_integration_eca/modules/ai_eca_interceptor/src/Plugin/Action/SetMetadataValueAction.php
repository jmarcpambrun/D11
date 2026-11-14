<?php

declare(strict_types=1);

namespace Drupal\ai_eca_interceptor\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;

/**
 * Action: set a metadata value on the AI event.
 */
#[Action(
  id: 'ai_eca_interceptor_set_metadata',
  label: new TranslatableMarkup('AI event: set metadata value'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Set a metadata key/value pair on the AI event.'),
  version_introduced: '2.0.0',
)]
class SetMetadataValueAction extends AiEventActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $object = NULL): void {
    $event = $this->aiEvent();
    if (!$event) {
      return;
    }
    // replaceClear(), matching ::access(). See the note there. Only the key is
    // resolved this way: it is the value the gate below tests for emptiness.
    // The metadata value itself is passed straight through, so a plain
    // replace() is right for it — an unresolvable token there is the site
    // builder's literal string, not a failed gate.
    $key = trim((string) $this->tokenService->replaceClear($this->configuration['key'] ?? ''));
    if ($key === '') {
      return;
    }
    $event->setMetadata($key, $this->tokenService->replace($this->configuration['value']));
  }

  /**
   * {@inheritdoc}
   *
   * The key is resolved with replaceClear(), not replace(): the plain replace()
   * leaves a token LITERAL when no replacement value can be generated, so a key
   * that resolves to nothing survives as a non-empty string, passes the check
   * below, and then writes event metadata under a garbage key such as
   * "[event:missing]" that no later condition will ever read. replaceClear()
   * removes such tokens, which is what makes the emptiness check meaningful.
   * ::execute() uses the same method — the two disagreeing is the exact bug
   * this check exists to prevent.
   *
   * Each denial carries its own specific reason so ECA can report it:
   * EcaAction::execute() logs it and the process debugger records the action as
   * access-denied rather than executed.
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($this->aiEvent() === NULL) {
      $result = AccessResult::forbidden('This action is only available on an AI request, response or failure event.');
    }
    elseif (trim((string) $this->tokenService->replaceClear($this->configuration['key'] ?? '')) === '') {
      $result = AccessResult::forbidden('The metadata key is empty after token replacement.');
    }
    else {
      $result = AccessResult::allowed();
    }
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['key' => '', 'value' => ''] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Metadata key'),
      '#default_value' => $this->configuration['key'],
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    $form['value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Value'),
      '#default_value' => $this->configuration['value'],
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['key'] = $form_state->getValue('key');
    $this->configuration['value'] = $form_state->getValue('value');
    parent::submitConfigurationForm($form, $form_state);
  }

}
