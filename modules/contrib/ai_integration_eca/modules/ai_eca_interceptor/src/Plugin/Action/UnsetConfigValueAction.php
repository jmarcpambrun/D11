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
 * Action: unset a provider configuration value on a pre-request event.
 */
#[Action(
  id: 'ai_eca_interceptor_unset_config',
  label: new TranslatableMarkup('AI request: unset config value'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Remove a provider configuration key from the current AI request.'),
  version_introduced: '2.0.0',
)]
class UnsetConfigValueAction extends AiEventActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $object = NULL): void {
    $event = $this->preEvent();
    if (!$event) {
      return;
    }
    // replaceClear(), matching ::access(). See the note there.
    $key = trim((string) $this->tokenService->replaceClear($this->configuration['key'] ?? ''));
    if ($key === '') {
      return;
    }
    $config = $event->getConfiguration();
    unset($config[$key]);
    $event->setConfiguration($config);
  }

  /**
   * {@inheritdoc}
   *
   * The key is resolved with replaceClear(), not replace(): the plain replace()
   * leaves a token LITERAL when no replacement value can be generated, so a key
   * that resolves to nothing survives as a non-empty string, passes the check
   * below, and then unsets a configuration key that cannot exist — the action
   * reports success while removing nothing. replaceClear() removes such tokens,
   * which is what makes the emptiness check meaningful. ::execute() uses the
   * same method — the two disagreeing is the exact bug this check exists to
   * prevent.
   *
   * Each denial carries its own specific reason so ECA can report it:
   * EcaAction::execute() logs it and the process debugger records the action as
   * access-denied rather than executed.
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($this->preEvent() === NULL) {
      $result = AccessResult::forbidden('This action is only available on the "AI request: before provider call" event.');
    }
    elseif (trim((string) $this->tokenService->replaceClear($this->configuration['key'] ?? '')) === '') {
      $result = AccessResult::forbidden('The configuration key is empty after token replacement.');
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
    return ['key' => ''] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Configuration key'),
      '#default_value' => $this->configuration['key'],
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['key'] = $form_state->getValue('key');
    parent::submitConfigurationForm($form, $form_state);
  }

}
