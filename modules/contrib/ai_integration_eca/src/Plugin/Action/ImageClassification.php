<?php

namespace Drupal\ai_integration_eca\Plugin\Action;

use Drupal\ai\Exception\AiOperationTypeMissingException;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\ImageClassification\ImageClassificationInput;
use Drupal\ai_integration_eca\Service\AiFileHelperInterface;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Describes the ai_integration_eca_execute_image_classification action.
 *
 * @Action(
 *   id = "ai_integration_eca_execute_image_classification",
 *   label = @Translation("Image Classification"),
 *   description = @Translation("Classify an image, returning labels with confidence scores.")
 * )
 */
class ImageClassification extends AiConfigActionBase {

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
      'labels' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['input_type'] = $this->fileHelper->inputElement($this->configuration['input_type']);
    $form['labels'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Candidate labels'),
      '#default_value' => $this->configuration['labels'],
      '#description' => $this->t('Optional candidate labels, one per line. Leave empty to let the model decide.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['input_type'] = $form_state->getValue('input_type');
    $this->configuration['labels'] = $form_state->getValue('labels');
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

    $labels = array_values(array_filter(array_map('trim', explode("\n", $this->configuration['labels']))));
    $input = new ImageClassificationInput($image, $labels);
    $provider->setConfiguration($this->getModelConfig());
    try {
      $items = $provider->imageClassification($input, $modelData['model_id'])->getNormalized();
    }
    catch (AiOperationTypeMissingException $e) {
      return;
    }

    // Emit a numeric list of uniform records rather than keying by label: the
    // labels are arbitrary model output, and using them as DTO property names
    // makes scores impossible to reference as tokens (spaces/punctuation) and
    // drops duplicates.
    $result = [];
    foreach ($items as $item) {
      $result[] = [
        'label' => $item->getLabel(),
        'confidence' => $item->getConfidenceScore(),
      ];
    }
    $this->tokenService->addTokenData($this->configuration['token_result'], DataTransferObject::create($result));
  }

  /**
   * {@inheritdoc}
   */
  protected function getOperationType(): string {
    return 'image_classification';
  }

}
