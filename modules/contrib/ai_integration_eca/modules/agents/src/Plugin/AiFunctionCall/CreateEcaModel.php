<?php

namespace Drupal\ai_integration_eca_agents\Plugin\AiFunctionCall;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Exception\MissingDataException;
use Drupal\Core\Url;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Exception\AiFunctionCallingExecutionError;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai_integration_eca_agents\EcaAccessTrait;
use Drupal\ai_integration_eca_agents\EntityViolationException;
use Drupal\ai_integration_eca_agents\MissingEventException;
use Drupal\ai_integration_eca_agents\Services\EcaRepository\EcaRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the create ECA model function.
 */
#[FunctionCall(
  id: 'ai_integration_eca:create_model',
  function_name: 'eca_create_model',
  name: 'Create ECA Model',
  description: 'Creates a new, disabled ECA model from a JSON definition that complies with the ECA model JSON Schema. This can only create new models, it can never change or delete an existing one.',
  group: 'modification_tools',
  module_dependencies: ['eca'],
  context_definitions: [
    'model' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Model"),
      description: new TranslatableMarkup("The complete ECA model as a JSON encoded object, complying with the ECA model JSON Schema. The model_id has to be a new, unused machine name."),
      required: TRUE,
    ),
  ],
)]
final class CreateEcaModel extends FunctionCallBase implements ExecutableFunctionCallInterface {

  use EcaAccessTrait;

  /**
   * The ECA repository.
   *
   * @var \Drupal\ai_integration_eca_agents\Services\EcaRepository\EcaRepositoryInterface
   */
  protected EcaRepositoryInterface $ecaRepository;

  /**
   * The information about what was created.
   *
   * @var string
   */
  protected string $information = '';

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): FunctionCallInterface|static {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.context_definition_normalizer'),
    );
    $instance->ecaRepository = $container->get('ai_integration_eca_agents.services.eca_repository');
    $instance->currentUser = $container->get('current_user');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    $this->checkEcaAccess();

    $payload = $this->decodeModel($this->getContextValue('model'));

    // This tool is create only. Refuse to touch a model that already exists,
    // so that an agent can never overwrite the work of somebody else.
    $modelId = $payload['model_id'] ?? '';
    if (is_string($modelId) && $modelId !== '' && $this->ecaRepository->get($modelId) !== NULL) {
      throw new AiFunctionCallingExecutionError(sprintf(
        'The ECA model "%s" already exists. This tool can only create new models, it can not change existing ones. Pick another, unused model_id if you want to create a new model.',
        $modelId
      ));
    }

    try {
      $eca = $this->ecaRepository->build($payload);
    }
    catch (MissingDataException | EntityViolationException $e) {
      throw new AiFunctionCallingExecutionError(sprintf(
        "The ECA model is not valid and was not created. Fix the following and call this tool again:\n%s",
        $e->getMessage()
      ));
    }
    catch (MissingEventException $e) {
      throw new AiFunctionCallingExecutionError(sprintf(
        'The ECA model is not valid and was not created: %s An ECA model needs at least one event that starts it.',
        $e->getMessage()
      ));
    }

    $this->information = sprintf(
      'The ECA model "%s" (%s) was created. It is disabled, so a human has to review and enable it. It can be reviewed at %s.',
      $eca->label(),
      $eca->id(),
      Url::fromRoute('entity.eca.collection')->toString()
    );
  }

  /**
   * Decode the model that the LLM provided.
   *
   * @param mixed $model
   *   The raw value of the model context.
   *
   * @return array
   *   Returns the decoded model.
   *
   * @throws \Drupal\ai\Exception\AiFunctionCallingExecutionError
   */
  protected function decodeModel(mixed $model): array {
    if (is_array($model)) {
      return $model;
    }

    if (!is_string($model) || trim($model) === '') {
      throw new AiFunctionCallingExecutionError('The model has to be given as a JSON encoded object.');
    }

    try {
      $payload = json_decode($model, TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $e) {
      throw new AiFunctionCallingExecutionError(sprintf(
        'The model could not be parsed as JSON: %s. Provide a RFC8259 compliant JSON object.',
        $e->getMessage()
      ));
    }

    if (!is_array($payload)) {
      throw new AiFunctionCallingExecutionError('The model has to be a JSON object, not a scalar value.');
    }

    return $payload;
  }

  /**
   * {@inheritdoc}
   */
  public function getReadableOutput(): string {
    return $this->information;
  }

}
