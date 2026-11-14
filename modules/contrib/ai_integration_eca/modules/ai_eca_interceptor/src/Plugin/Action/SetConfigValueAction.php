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
 * Action: set a provider configuration value on a pre-request event.
 */
#[Action(
  id: 'ai_eca_interceptor_set_config',
  label: new TranslatableMarkup('AI request: set config value'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Set a provider configuration value (e.g. temperature, max_tokens).'),
  version_introduced: '2.0.0',
)]
class SetConfigValueAction extends AiEventActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $object = NULL): void {
    $event = $this->preEvent();
    if (!$event) {
      return;
    }
    // replaceClear(), matching ::access(). See the note there. Only the key is
    // resolved this way: it is the value the gate below tests for emptiness.
    // The configuration value itself is passed straight through, so a plain
    // replace() is right for it — an unresolvable token there is the site
    // builder's literal string, not a failed gate.
    $key = trim((string) $this->tokenService->replaceClear($this->configuration['key'] ?? ''));
    if ($key === '') {
      return;
    }
    $value = $this->tokenService->replace($this->configuration['value']);
    if ($this->configuration['cast'] === 'int') {
      $value = (int) $value;
    }
    elseif ($this->configuration['cast'] === 'float') {
      $value = (float) $value;
    }
    elseif ($this->configuration['cast'] === 'bool') {
      $value = filter_var($value, FILTER_VALIDATE_BOOL);
    }
    $config = $event->getConfiguration();
    $config[$key] = $value;
    $event->setConfiguration($config);
  }

  /**
   * {@inheritdoc}
   *
   * The key is resolved with replaceClear(), not replace(): the plain replace()
   * leaves a token LITERAL when no replacement value can be generated, so a key
   * that resolves to nothing survives as a non-empty string, passes the check
   * below, and then writes provider configuration under a garbage key such as
   * "[event:missing]". replaceClear() removes such tokens, which is what makes
   * the emptiness check meaningful. ::execute() uses the same method — the two
   * disagreeing is the exact bug this check exists to prevent.
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
    return [
      'key' => '',
      'value' => '',
      'cast' => 'string',
    ] + parent::defaultConfiguration();
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
    $form['value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Configuration value'),
      '#default_value' => $this->configuration['value'],
      '#eca_token_replacement' => TRUE,
    ];
    $form['cast'] = [
      '#type' => 'select',
      '#title' => $this->t('Cast to'),
      '#options' => [
        'string' => $this->t('String'),
        'int' => $this->t('Integer'),
        'float' => $this->t('Float'),
        'bool' => $this->t('Boolean'),
      ],
      '#default_value' => $this->configuration['cast'],
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['key'] = $form_state->getValue('key');
    $this->configuration['value'] = $form_state->getValue('value');
    $this->configuration['cast'] = $form_state->getValue('cast');
    parent::submitConfigurationForm($form, $form_state);
  }

}
