<?php

namespace Drupal\ai_integration_eca\Plugin\Action;

use Drupal\ai\OperationType\GenericType\AudioFile;
use Drupal\ai_integration_eca\Service\AiFileHelperInterface;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Describes the ai_integration_eca_execute_speech action.
 *
 * @Action(
 *   id = "ai_integration_eca_execute_stt",
 *   label = @Translation("Speech to Text"),
 *   description = @Translation("Process an audio file through the AI speech-to-text endpoint.")
 * )
 */
class SpeechToText extends AiConfigActionBase {

  /**
   * The file helper.
   *
   * @var \Drupal\ai_integration_eca\Service\AiFileHelperInterface
   */
  protected AiFileHelperInterface $fileHelper;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->fileHelper = $container->get('ai_integration_eca.file_helper');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return parent::defaultConfiguration() + [
      'input_type' => AiFileHelperInterface::INPUT_PATH,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['input_type'] = $this->fileHelper->inputElement($this->configuration['input_type']);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['input_type'] = $form_state->getValue('input_type');
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    // Read through the file helper rather than opening the token value here,
    // so this action takes the same three input sources, and applies the same
    // constraints to them, as every other file-based action.
    try {
      $audio = $this->fileHelper->resolveInput(
        $this->resolveTokenString($this->configuration['token_input']),
        $this->configuration['input_type'] ?? AiFileHelperInterface::INPUT_PATH,
        AudioFile::class,
      );
    }
    catch (\InvalidArgumentException $e) {
      return;
    }

    $binary = $audio->getBinary();
    if ($binary === '') {
      return;
    }

    $modelData = $this->getModelData();
    try {
      $provider = $this->loadModelProvider();
    }
    catch (PluginException $e) {
      return;
    }

    $provider->setConfiguration($this->getModelConfig());
    $response = $provider->speechToText($binary, $modelData['model_id'], ['ai_integration_eca']);

    $this->tokenService->addTokenData($this->configuration['token_result'], $response->getNormalized());
  }

  /**
   * {@inheritdoc}
   */
  protected function getOperationType(): string {
    return 'speech_to_text';
  }

}
