<?php

declare(strict_types=1);

namespace Drupal\ai_eca_interceptor\Plugin\Action;

use Drupal\ai\Guardrail\AiGuardrailSetInterface;
use Drupal\ai\OperationType\InputInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;

/**
 * Action: attach a guardrail set to the current AI request.
 *
 * Operates on PreGenerateResponseEvent. Loads the chosen guardrail set config
 * entity and calls InputInterface::addGuardrailSet() so that the core
 * GuardrailsEventSubscriber applies its pre- and post-generate guardrails
 * automatically. Adding (rather than replacing) preserves any guardrail sets
 * already attached to the request.
 */
#[Action(
  id: 'ai_eca_interceptor_attach_guardrail_set',
  label: new TranslatableMarkup('AI request: attach guardrail set'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Attach a guardrail set to the current AI request so its pre- and post-generate guardrails are applied.'),
  version_introduced: '2.0.0',
)]
class AttachGuardrailSetAction extends AiEventActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $object = NULL): void {
    $event = $this->preEvent();
    if (!$event) {
      return;
    }
    $input = $event->getInput();
    if (!$input instanceof InputInterface) {
      return;
    }
    // replaceClear(), matching ::access(). See the note there.
    $guardrail_set_id = trim((string) $this->tokenService->replaceClear($this->configuration['guardrail_set_id'] ?? ''));
    if ($guardrail_set_id === '') {
      return;
    }
    $guardrail_set = $this->entityTypeManager
      ->getStorage('ai_guardrail_set')
      ->load($guardrail_set_id);
    if (!$guardrail_set instanceof AiGuardrailSetInterface) {
      return;
    }
    $input->addGuardrailSet($guardrail_set);
  }

  /**
   * {@inheritdoc}
   *
   * ECA sets the event (setEvent()) before calling access(), so token
   * replacement resolves against the live request here. Performing the checks
   * in access() means invalid configuration — an empty ID or an unknown
   * guardrail set — is reported as access denied and logged by ECA, instead of
   * being silently skipped inside execute().
   *
   * Resolution uses replaceClear(), not replace(): the plain replace() leaves a
   * token LITERAL when no replacement value can be generated, so an ID that
   * resolves to nothing survives as a non-empty string and passes the emptiness
   * check below. replaceClear() removes such tokens, which is what makes that
   * check meaningful. ::execute() uses the same method — the two disagreeing is
   * the exact bug this check exists to prevent.
   *
   * Every denial carries its own specific reason so ECA can report it:
   * EcaAction::execute() logs it and the process debugger records the action as
   * access-denied rather than executed.
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $event = $this->preEvent();
    if ($event === NULL) {
      $result = AccessResult::forbidden('This action is only available on the "AI request: before provider call" event.');
    }
    elseif (!$event->getInput() instanceof InputInterface) {
      $result = AccessResult::forbidden('The AI request input does not support guardrail sets.');
    }
    else {
      $guardrail_set_id = trim((string) $this->tokenService->replaceClear($this->configuration['guardrail_set_id'] ?? ''));
      if ($guardrail_set_id === '') {
        $result = AccessResult::forbidden('The guardrail set ID is empty after token replacement.');
      }
      elseif (!$this->entityTypeManager->getStorage('ai_guardrail_set')->load($guardrail_set_id) instanceof AiGuardrailSetInterface) {
        $result = AccessResult::forbidden(sprintf('There is no guardrail set with the ID "%s".', $guardrail_set_id));
      }
      else {
        $result = AccessResult::allowed();
      }
    }
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['guardrail_set_id' => ''] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $options = $this->loadGuardrailSetOptions();
    $form['guardrail_set_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Guardrail set ID'),
      '#default_value' => $this->configuration['guardrail_set_id'],
      '#description' => $this->t('Machine name of the guardrail set to attach. Available sets: @sets', [
        '@sets' => $options ? implode(', ', array_map(
          fn(string $id, string $label) => "$id ($label)",
          array_keys($options),
          $options,
        )) : $this->t('none configured yet'),
      ]),
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['guardrail_set_id'] = $form_state->getValue('guardrail_set_id');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * Loads available guardrail set options keyed by ID.
   *
   * @return array<string, string>
   *   Guardrail set labels keyed by entity ID.
   */
  private function loadGuardrailSetOptions(): array {
    $sets = $this->entityTypeManager
      ->getStorage('ai_guardrail_set')
      ->loadMultiple();
    $options = [];
    foreach ($sets as $id => $set) {
      $options[$id] = (string) $set->label();
    }
    return $options;
  }

}
