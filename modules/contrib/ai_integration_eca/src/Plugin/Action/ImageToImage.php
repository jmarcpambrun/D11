<?php

namespace Drupal\ai_integration_eca\Plugin\Action;

use Drupal\ai\Exception\AiOperationTypeMissingException;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\ImageToImage\ImageToImageInput;
use Drupal\ai_integration_eca\Service\AiFileHelperInterface;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Describes the ai_integration_eca_execute_image_to_image action.
 *
 * @Action(
 *   id = "ai_integration_eca_execute_image_to_image",
 *   label = @Translation("Image to Image"),
 *   description = @Translation("Transform an input image into one or more images.")
 * )
 */
class ImageToImage extends AiConfigActionBase {

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
      'prompt' => '',
      'token_mask' => '',
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
    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Prompt'),
      '#default_value' => $this->configuration['prompt'],
      '#description' => $this->t('Optional instruction describing the transformation. Tokens are supported.'),
      '#eca_token_replacement' => TRUE,
    ];
    $form['token_mask'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mask'),
      '#default_value' => $this->configuration['token_mask'],
      '#description' => $this->t('Optional mask image, read with the same input source as the main image. Accepts a single token holding the value (e.g. <em>[my_mask]</em> or <em>[node:field_mask]</em>), or a value typed in directly.'),
      // Not "#eca_token_reference": that flag contracts the field to the bare
      // name of an ECA token, and the modeler refuses "[name]" for it. This
      // field also takes a literal value and a Drupal token, both of which
      // have to be written out in full.
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
    $this->configuration['input_type'] = $form_state->getValue('input_type');
    $this->configuration['prompt'] = $form_state->getValue('prompt');
    $this->configuration['token_mask'] = $form_state->getValue('token_mask');
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
      $image = $this->fileHelper->resolveInput($this->resolveTokenString($this->configuration['token_input']), $this->configuration['input_type'], ImageFile::class);
    }
    catch (\InvalidArgumentException $e) {
      return;
    }

    $input = new ImageToImageInput($image);
    if ($this->configuration['prompt'] !== '') {
      $input->setPrompt($this->tokenService->replace($this->configuration['prompt']));
    }
    // Resolve first, then ask whether anything came of it. Asking the ECA token
    // stack whether it holds the configured value, as this used to, answers no
    // for a Drupal token and for a literal path, and the mask was dropped
    // without a word.
    $mask = $this->resolveTokenString($this->configuration['token_mask'] ?? '');
    if ($mask !== '') {
      try {
        $input->setMask($this->fileHelper->resolveInput($mask, $this->configuration['input_type'], ImageFile::class));
      }
      catch (\InvalidArgumentException $e) {
        // Ignore an unreadable mask; it is optional.
      }
    }

    $provider->setConfiguration($this->getModelConfig());
    try {
      $files = $provider->imageToImage($input, $modelData['model_id'])->getNormalized();
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
    return 'image_to_image';
  }

}
