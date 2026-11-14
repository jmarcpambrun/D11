<?php

namespace Drupal\ai_integration_eca_agents\Plugin\AiFunctionCall;

use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Exception\AiFunctionCallingExecutionError;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai_agents\PluginInterfaces\AiAgentContextInterface;
use Drupal\ai_integration_eca_agents\EcaAccessTrait;
use Drupal\ai_integration_eca_agents\Services\SchemaProvider\SchemaProviderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

/**
 * Plugin implementation of the get ECA model schema function.
 */
#[FunctionCall(
  id: 'ai_integration_eca:get_model_schema',
  function_name: 'eca_get_model_schema',
  name: 'Get ECA Model Schema',
  description: 'Returns the JSON Schema that an ECA model has to comply with when it is created.',
  group: 'information_tools',
  module_dependencies: ['eca'],
)]
final class GetEcaModelSchema extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

  use EcaAccessTrait;

  /**
   * The schema provider.
   *
   * @var \Drupal\ai_integration_eca_agents\Services\SchemaProvider\SchemaProviderInterface
   */
  protected SchemaProviderInterface $schemaProvider;

  /**
   * The generated schema.
   *
   * @var string
   */
  protected string $schema = '';

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
    $instance->schemaProvider = $container->get('ai_integration_eca_agents.services.schema_provider');
    $instance->currentUser = $container->get('current_user');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    $this->checkEcaAccess();

    try {
      $this->schema = $this->schemaProvider->getModelSchema();
    }
    catch (ExceptionInterface $e) {
      throw new AiFunctionCallingExecutionError(sprintf('The JSON Schema of the ECA model could not be generated: %s', $e->getMessage()));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getReadableOutput(): string {
    return sprintf("The JSON Schema of an ECA model:\n```json\n%s\n```", $this->schema);
  }

}
