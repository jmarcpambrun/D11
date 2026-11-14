<?php

namespace Drupal\ai_integration_eca\Plugin\Action;

use Drupal\ai\Exception\AiOperationTypeMissingException;
use Drupal\ai\OperationType\TranslateText\TranslateTextInput;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Form\FormStateInterface;

/**
 * Describes the ai_integration_eca_execute_translate_text action.
 *
 * @Action(
 *   id = "ai_integration_eca_execute_translate_text",
 *   label = @Translation("Translate Text"),
 *   description = @Translation("Translate text from one language to another.")
 * )
 */
class TranslateText extends AiConfigActionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return parent::defaultConfiguration() + [
      'text' => '',
      'source_language' => '',
      'target_language' => 'en',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Text to translate'),
      '#default_value' => $this->configuration['text'],
      '#description' => $this->t('The text to translate. Tokens are supported, e.g. "[node:title]". If left empty, the value of the input token is translated instead.'),
      '#eca_token_replacement' => TRUE,
    ];
    $form['source_language'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Source language'),
      '#default_value' => $this->configuration['source_language'],
      '#description' => $this->t('Optional ISO-639-1 source language code. Leave empty to auto-detect.'),
    ];
    $form['target_language'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Target language'),
      '#default_value' => $this->configuration['target_language'],
      '#description' => $this->t('ISO-639-1 target language code, e.g. sv.'),
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['text'] = $form_state->getValue('text');
    $this->configuration['source_language'] = $form_state->getValue('source_language');
    $this->configuration['target_language'] = $form_state->getValue('target_language');
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    $modelData = $this->getModelData();
    try {
      $provider = $this->loadModelProvider();
    }
    catch (PluginException $e) {
      return;
    }

    // The text to translate comes from the "Text" field (tokens supported). If
    // it is left empty, fall back to the value of the configured input token.
    $text = $this->configuration['text'] !== ''
      ? (string) $this->tokenService->replace($this->configuration['text'])
      : $this->resolveTokenString($this->configuration['token_input']);
    $source = $this->configuration['source_language'] !== '' ? $this->configuration['source_language'] : NULL;

    $input = new TranslateTextInput($text, $source, $this->configuration['target_language']);
    $provider->setConfiguration($this->getModelConfig());
    try {
      $translated = $provider->translateText($input, $modelData['model_id'])->getNormalized();
    }
    catch (AiOperationTypeMissingException $e) {
      return;
    }

    $this->tokenService->addTokenData($this->configuration['token_result'], $translated);
  }

  /**
   * {@inheritdoc}
   */
  protected function getOperationType(): string {
    return 'translate_text';
  }

}
