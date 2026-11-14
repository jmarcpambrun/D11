<?php

namespace Drupal\ai_integration_eca\Plugin\Action;

use Drupal\ai\Exception\AiOperationTypeMissingException;
use Drupal\ai\OperationType\TextToImage\TextToImageInput;
use Drupal\ai_integration_eca\Service\AiFileHelperInterface;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Describes the ai_integration_eca_execute_text_to_image action.
 *
 * @Action(
 *   id = "ai_integration_eca_execute_text_to_image",
 *   label = @Translation("Text to Image"),
 *   description = @Translation("Generate one or more images from a text prompt.")
 * )
 */
class TextToImage extends AiConfigActionBase {

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
    $defaults = parent::defaultConfiguration();
    unset($defaults['token_input']);
    return $defaults + [
      'prompt' => 'Enter your image prompt here.',
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
    unset($form['token_input']);
    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Prompt'),
      '#default_value' => $this->configuration['prompt'],
      '#description' => $this->t('The text prompt describing the image to generate. Tokens are supported, e.g. "an image that looks like [node:title]".'),
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
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
    unset($this->configuration['token_input']);
    $this->configuration['prompt'] = $form_state->getValue('prompt');
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

    // Resolve any tokens embedded in the prompt against the full ECA token
    // scope (the same way ECA's own free-text actions do).
    $prompt = $this->tokenService->replace($this->configuration['prompt']);

    $provider->setConfiguration($this->getModelConfig());
    $input = new TextToImageInput($prompt);
    try {
      $files = $provider->textToImage($input, $modelData['model_id'])->getNormalized();
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
    return 'text_to_image';
  }

}
