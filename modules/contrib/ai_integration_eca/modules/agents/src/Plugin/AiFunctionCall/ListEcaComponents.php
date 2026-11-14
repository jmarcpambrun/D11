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
use Drupal\ai_agents\PluginInterfaces\AiAgentContextInterface;
use Drupal\ai_integration_eca_agents\EcaAccessTrait;
use Drupal\ai_integration_eca_agents\Services\DataProvider\DataProviderInterface;
use Drupal\ai_integration_eca_agents\Services\DataProvider\DataViewModeEnum;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the list ECA components function.
 */
#[FunctionCall(
  id: 'ai_integration_eca:list_components',
  function_name: 'eca_list_components',
  name: 'List ECA Components',
  description: 'Lists the events, conditions and actions that are available on this website and that can be used as building blocks of an ECA model. This only returns the plugin id, name and description of each component, use the tool to get component details for their configuration options.',
  group: 'information_tools',
  module_dependencies: ['eca'],
  context_definitions: [
    'component_types' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Component types"),
      description: new TranslatableMarkup('Only list components of these types. Allowed values are "events", "conditions" and "actions". Leave empty to list all of them.'),
      required: FALSE,
      multiple: TRUE,
    ),
    'search' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Search"),
      description: new TranslatableMarkup("An optional case insensitive search string. Only components whose plugin id, name or description contain this string are listed."),
      required: FALSE,
    ),
  ],
)]
final class ListEcaComponents extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

  use EcaAccessTrait;

  /**
   * The ECA data provider.
   *
   * @var \Drupal\ai_integration_eca_agents\Services\DataProvider\DataProviderInterface
   */
  protected DataProviderInterface $dataProvider;

  /**
   * The components, keyed by their plural type.
   *
   * @var array
   */
  protected array $components = [];

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

    $types = array_filter((array) ($this->getContextValue('component_types') ?? []));
    $allowed = ['events', 'conditions', 'actions'];
    if ($invalid = array_diff($types, $allowed)) {
      throw new AiFunctionCallingExecutionError(sprintf(
        'Unknown component types: %s. Allowed values are: %s.',
        implode(', ', $invalid),
        implode(', ', $allowed)
      ));
    }
    $search = trim((string) ($this->getContextValue('search') ?? ''));

    $this->dataProvider->setViewMode(DataViewModeEnum::Teaser);
    foreach ($this->dataProvider->getComponents() as $type => $components) {
      if (!empty($types) && !in_array($type, $types, TRUE)) {
        continue;
      }
      if ($search !== '') {
        $components = array_values(array_filter($components, static function (array $component) use ($search): bool {
          $haystack = implode(' ', array_filter([
            $component['plugin_id'] ?? '',
            $component['name'] ?? '',
            $component['description'] ?? '',
          ]));

          return stripos($haystack, $search) !== FALSE;
        }));
      }

      $this->components[$type] = $components;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getReadableOutput(): string {
    $count = array_sum(array_map('count', $this->components));
    if ($count === 0) {
      return 'There are no ECA components that match the given criteria.';
    }

    return sprintf(
      "The ECA components that can be used in a model:\n```json\n%s\n```",
      Json::encode($this->components)
    );
  }

}
