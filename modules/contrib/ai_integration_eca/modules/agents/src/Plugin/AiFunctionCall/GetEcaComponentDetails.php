<?php

namespace Drupal\ai_integration_eca_agents\Plugin\AiFunctionCall;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Exception\AiFunctionCallingExecutionError;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai_integration_eca_agents\EcaAccessTrait;
use Drupal\ai_integration_eca_agents\Services\DataProvider\DataProviderInterface;
use Drupal\ai_integration_eca_agents\Services\DataProvider\DataViewModeEnum;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the get ECA component details function.
 */
#[FunctionCall(
  id: 'ai_integration_eca:get_component_details',
  function_name: 'eca_get_component_details',
  name: 'Get ECA Component Details',
  description: 'Returns the full details of one or more ECA events, conditions or actions, including every configuration key that the component accepts and the tokens that an event exposes. Always call this for every component before using it in a model, so that the configuration is filled in correctly.',
  group: 'information_tools',
  module_dependencies: ['eca'],
  context_definitions: [
    'component_ids' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Component IDs"),
      description: new TranslatableMarkup('The plugin ids of the events, conditions or actions to describe, for example "content_entity:insert" or "eca_set_field_value".'),
      required: TRUE,
      multiple: TRUE,
    ),
  ],
)]
final class GetEcaComponentDetails extends FunctionCallBase implements ExecutableFunctionCallInterface {

  use EcaAccessTrait;

  /**
   * The ECA data provider.
   *
   * @var \Drupal\ai_integration_eca_agents\Services\DataProvider\DataProviderInterface
   */
  protected DataProviderInterface $dataProvider;

  /**
   * The components that were found, keyed by their plural type.
   *
   * @var array
   */
  protected array $components = [];

  /**
   * The component ids that could not be found.
   *
   * @var string[]
   */
  protected array $unknown = [];

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
    $instance->dataProvider = $container->get('ai_integration_eca_agents.services.data_provider');
    $instance->currentUser = $container->get('current_user');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    $this->checkEcaAccess();

    $ids = array_values(array_unique(array_filter((array) $this->getContextValue('component_ids'))));
    if (empty($ids)) {
      throw new AiFunctionCallingExecutionError('At least one component id has to be given.');
    }

    $this->dataProvider->setViewMode(DataViewModeEnum::Full);
    $components = $this->dataProvider->getComponents($ids);

    $found = [];
    foreach ($components as $type => $items) {
      if (empty($items)) {
        continue;
      }
      $this->components[$type] = $items;
      foreach ($items as $item) {
        $found[] = $item['plugin_id'];
      }
    }

    $this->unknown = array_values(array_diff($ids, $found));
  }

  /**
   * {@inheritdoc}
   */
  public function getReadableOutput(): string {
    $output = [];

    if (!empty($this->components)) {
      $output[] = sprintf(
        "The details of the requested ECA components:\n```json\n%s\n```",
        Json::encode($this->components)
      );
    }

    if (!empty($this->unknown)) {
      $output[] = sprintf(
        'The following component ids do not exist on this website and can not be used in a model: %s. Use the tool that lists the ECA components to find a component that does exist.',
        implode(', ', $this->unknown)
      );
    }

    return implode("\n\n", $output);
  }

}
