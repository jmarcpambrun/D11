<?php

namespace Drupal\ai_integration_eca\Plugin\Action;

use Drupal\ai\Exception\AiOperationTypeMissingException;
use Drupal\ai\OperationType\GenericType\AudioFile;
use Drupal\ai\OperationType\SpeechToSpeech\SpeechToSpeechInput;
use Drupal\ai_integration_eca\Service\AiFileHelperInterface;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Describes the ai_integration_eca_execute_speech_to_speech action.
 *
 * @Action(
 *   id = "ai_integration_eca_execute_speech_to_speech",
 *   label = @Translation("Speech to Speech"),
 *   description = @Translation("Transform an input audio file into one or more audio files.")
 * )
 */
class SpeechToSpeech extends AiConfigActionBase {

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
      'output_type' => AiFileHelperInterface::OUTPUT_MANAGED_FILE,
      'destination' => 'public://',
      'filename' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['input_type'] = $this->fileHelper->inputElement($this->configuration['input_type']);
    $form['output_type'] = $this->fileHelper->outputElement($this->configuration['output_type']);
    $form['destination'] = $this->fileHelper->destinationElement($this->configuration['destination']);
    $form['filename'] = $this->fileHelper->filenameElement($this->configuration['filename']);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['input_type'] = $form_state->getValue('input_type');
    $this->configuration['output_type'] = $form_state->getValue('output_type');
    $this->configuration['destination'] = $form_state->getValue('destination');
    $this->configuration['filename'] = $form_state->getValue('filename');
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

    try {
      $audio = $this->fileHelper->resolveInput($this->resolveTokenString($this->configuration['token_input']), $this->configuration['input_type'], AudioFile::class);
    }
    catch (\InvalidArgumentException $e) {
      return;
    }

    $input = new SpeechToSpeechInput($audio);
    $provider->setConfiguration($this->getModelConfig());
    try {
      $files = $provider->speechToSpeech($input, $modelData['model_id'])->getNormalized();
    }
    catch (AiOperationTypeMissingException $e) {
      return;
    }
    $output = $this->fileHelper->buildOutput($files, $this->configuration['output_type'], $this->configuration['destination'], $this->tokenService->replace($this->configuration['filename']));
    $this->tokenService->addTokenData($this->configuration['token_result'], DataTransferObject::create($output));
  }

  /**
   * {@inheritdoc}
   */
  protected function getOperationType(): string {
    return 'speech_to_speech';
  }

}
