<?php

namespace Drupal\ai_integration_eca_agents\Plugin\AiFunctionCall;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai_agents\PluginInterfaces\AiAgentContextInterface;
use Drupal\ai_integration_eca_agents\EcaAccessTrait;
use Drupal\ai_integration_eca_agents\Services\DataProvider\DataProviderInterface;
use Drupal\ai_integration_eca_agents\Services\DataProvider\DataViewModeEnum;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the list ECA models function.
 */
#[FunctionCall(
  id: 'ai_integration_eca:list_models',
  function_name: 'eca_list_models',
  name: 'List ECA Models',
  description: 'Lists the ECA models that already exist on this website. Without model ids only the id, label and description of every model is returned, with model ids the complete definition of those models is returned.',
  group: 'information_tools',
  module_dependencies: ['eca'],
  context_definitions: [
    'model_ids' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Model IDs"),
      description: new TranslatableMarkup("The ids of the models to return in full detail. Leave empty to get a summary of every model."),
      required: FALSE,
      multiple: TRUE,
    ),
  ],
)]
final class ListEcaModels extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

  use EcaAccessTrait;

  /**
   * The ECA data provider.
   *
   * @var \Drupal\ai_integration_eca_agents\Services\DataProvider\DataProviderInterface
   */
  protected DataProviderInterface $dataProvider;

  /**
   * The models that were found.
   *
   * @var array
   */
  protected array $models = [];

  /**
   * The model ids that could not be found.
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

    $ids = array_values(array_unique(array_filter((array) ($this->getContextValue('model_ids') ?? []))));

    $this->dataProvider->setViewMode(empty($ids) ? DataViewModeEnum::Teaser : DataViewModeEnum::Full);
    $this->models = $this->dataProvider->getModels($ids);

    $found = array_column($this->models, 'model_id');
    $this->unknown = array_values(array_diff($ids, $found));
  }

  /**
   * {@inheritdoc}
   */
  public function getReadableOutput(): string {
    $output = [];

    if (empty($this->models)) {
      $output[] = 'There are no ECA models on this website yet.';
    }
    else {
      $output[] = sprintf(
        "The ECA models that exist on this website:\n```json\n%s\n```",
        Json::encode($this->models)
      );
    }

    if (!empty($this->unknown)) {
      $output[] = sprintf(
        'The following model ids do not exist on this website: %s.',
        implode(', ', $this->unknown)
      );
    }

    return implode("\n\n", $output);
  }

}
