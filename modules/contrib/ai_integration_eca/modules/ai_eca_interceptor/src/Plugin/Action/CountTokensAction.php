<?php

declare(strict_types=1);

namespace Drupal\ai_eca_interceptor\Plugin\Action;

use Drupal\ai\Event\AiProviderRequestBaseEvent;
use Drupal\ai\Utility\TokenizerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Action: count tokens for a given text using the AI Tokenizer.
 *
 * Writes the integer token count into a named token so later conditions and
 * actions can branch on it. Can be used standalone or inside an AI event
 * flow. When run inside an AI event flow with no model configured, it falls
 * back to the model id carried on the event.
 */
#[Action(
  id: 'ai_eca_interceptor_count_tokens',
  label: new TranslatableMarkup('AI: count tokens'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Count tokens for a text using the AI Tokenizer and store the integer count in a named token.'),
  version_introduced: '2.0.0',
)]
class CountTokensAction extends ConfigurableActionBase {

  /**
   * The AI tokenizer.
   *
   * @var \Drupal\ai\Utility\TokenizerInterface
   */
  protected TokenizerInterface $tokenizer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->tokenizer = $container->get('ai.tokenizer');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $object = NULL): void {
    $tokenName = trim((string) $this->configuration['token_name']);
    if ($tokenName === '') {
      return;
    }
    $text = (string) $this->tokenService->replace($this->configuration['text']);
    $model = trim((string) $this->tokenService->replace($this->configuration['model']));
    if ($model === '') {
      $event = $this->getEvent();
      if ($event instanceof AiProviderRequestBaseEvent) {
        $model = $event->getModelId();
      }
    }
    if ($model === '') {
      // Tokenizer still needs a model; it falls back internally when unknown,
      // but it requires a call to setModel() first. Use a sensible default.
      $model = 'gpt-3.5-turbo';
    }

    try {
      $this->tokenizer->setModel($model);
      $count = $this->tokenizer->countTokens($text);
    }
    catch (\Throwable $e) {
      $this->logger->error('Token count failed: @message', ['@message' => $e->getMessage()]);
      return;
    }
    $this->tokenService->addTokenData($tokenName, $count);
  }

  /**
   * {@inheritdoc}
   *
   * The configured result token name is deliberately NOT run through token
   * replacement, here or in ::execute(): it names the token this action writes
   * to (the form marks it '#eca_token_reference'), it is not a value carrying
   * tokens to resolve. Both sides read the raw configuration, so they agree.
   *
   * The denial carries a reason so ECA can report it: EcaAction::execute() logs
   * it and the process debugger records the action as access-denied rather than
   * executed.
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $tokenName = trim((string) ($this->configuration['token_name'] ?? ''));
    if ($tokenName === '') {
      $result = AccessResult::forbidden('No result token name is configured, so the token count would have nowhere to go.');
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
      'text' => '',
      'model' => '',
      'token_name' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Text'),
      '#default_value' => $this->configuration['text'],
      '#description' => $this->t('The text to count tokens for. Token replacement is supported, so you can pass e.g. [event:input_text].'),
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    $form['model'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Model id'),
      '#default_value' => $this->configuration['model'],
      '#description' => $this->t('The model id used to select the tokenizer encoding. Leave empty to use the AI event model id when running inside an AI event flow.'),
      '#eca_token_replacement' => TRUE,
    ];
    $form['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Result token name'),
      '#default_value' => $this->configuration['token_name'],
      '#description' => $this->t('The ECA token name to store the integer count in, e.g. "token_count".'),
      '#required' => TRUE,
      '#eca_token_reference' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['text'] = $form_state->getValue('text');
    $this->configuration['model'] = $form_state->getValue('model');
    $this->configuration['token_name'] = $form_state->getValue('token_name');
    parent::submitConfigurationForm($form, $form_state);
  }

}
