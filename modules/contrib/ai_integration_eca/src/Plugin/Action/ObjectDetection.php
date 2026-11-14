<?php

namespace Drupal\ai_integration_eca\Plugin\Action;

use Drupal\ai\Exception\AiOperationTypeMissingException;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\ObjectDetection\ObjectDetectionInput;
use Drupal\ai\OperationType\ObjectDetection\ObjectDetectionOutput;
use Drupal\ai_integration_eca\Service\AiFileHelperInterface;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Describes the ai_integration_eca_execute_object_detection action.
 *
 * @Action(
 *   id = "ai_integration_eca_execute_object_detection",
 *   label = @Translation("Object Detection"),
 *   description = @Translation("Detect objects in an image, returning labels, scores and bounding boxes.")
 * )
 */
class ObjectDetection extends AiConfigActionBase {

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

    $input = new ObjectDetectionInput($image);
    $provider->setConfiguration($this->getModelConfig());
    try {
      $output = $provider->objectDetection($input, $modelData['model_id']);
    }
    catch (AiOperationTypeMissingException $e) {
      return;
    }
    $this->tokenService->addTokenData($this->configuration['token_result'], DataTransferObject::create($this->normalizeOutput($output)));
  }

  /**
   * Map a detection output to a list of {label, confidence, box}.
   *
   * @param \Drupal\ai\OperationType\ObjectDetection\ObjectDetectionOutput $output
   *   The provider output.
   *
   * @return array
   *   The normalized list.
   */
  protected function normalizeOutput(ObjectDetectionOutput $output): array {
    $result = [];
    foreach ($output->getNormalized() as $item) {
      $result[] = [
        'label' => $item->getLabel(),
        'confidence' => $item->getConfidenceScore(),
        'box' => $item->getBox(),
      ];
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getOperationType(): string {
    return 'object_detection';
  }

}
