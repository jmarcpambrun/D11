<?php

namespace Drupal\ai_integration_eca_agents\Services\DataProvider;

use Drupal\ai_integration_eca_agents\EcaElementType;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\ai_integration_eca_agents\Services\ModelMapper\ModelMapperInterface;
use Drupal\eca\Attribute\Token;
use Drupal\eca\Entity\Eca;
use Drupal\eca\PluginManager\Event;
use Drupal\eca\Service\Actions;
use Drupal\eca\Service\Conditions;

/**
 * Class for providing ECA-data.
 */
class DataProvider implements DataProviderInterface {

  /**
   * The view mode which determines how many details are returned.
   *
   * @var \Drupal\ai_integration_eca_agents\Services\DataProvider\DataViewModeEnum
   */
  protected DataViewModeEnum $viewMode = DataViewModeEnum::Teaser;

  /**
   * Constructs an DataProvider instance.
   *
   * @param \Drupal\eca\PluginManager\Event $pluginManagerEvent
   *   The service for ECA modellers.
   * @param \Drupal\eca\Service\Conditions $conditions
   *   The conditions.
   * @param \Drupal\eca\Service\Actions $actions
   *   The actions.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\ai_integration_eca_agents\Services\ModelMapper\ModelMapperInterface $modelMapper
   *   The model mapper.
   */
  public function __construct(
    protected Event $pluginManagerEvent,
    protected Conditions $conditions,
    protected Actions $actions,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ModelMapperInterface $modelMapper,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function getEvents(): array {
    $output = [];
    foreach ($this->pluginManagerEvent->getDefinitions() as $plugin_id => $definition) {
      $info = [
        'plugin_id' => $plugin_id,
        'name' => $definition['label'],
        'module' => $definition['provider'],
      ];

      if ($this->viewMode === DataViewModeEnum::Full) {
        /** @var \Drupal\eca\Plugin\ECA\Event\EventInterface $event */
        $event = $this->pluginManagerEvent->createInstance($plugin_id);
        $info['exposed_tokens'] = array_reduce($event->getTokens(), function (array $carry, Token $token) {
          $info = [
            'name' => $token->name,
            'description' => $token->description,
          ];
          if (!empty($token->aliases)) {
            $info['aliases'] = implode(', ', $token->aliases);
          }
          $carry[] = $info;

          return $carry;
        }, []);

        $info['configuration'] = $this->buildConfig($event);
      }

      $output[] = $info;
    }

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function getConditions(): array {
    return $this->convertPlugins($this->conditions->conditions());
  }

  /**
   * {@inheritdoc}
   */
  public function getActions(): array {
    return $this->convertPlugins($this->actions->actions());
  }

  /**
   * {@inheritdoc}
   */
  public function getComponents(array $filterIds = []): array {
    $filterFunction = fn ($plugin) => in_array($plugin['plugin_id'], $filterIds, TRUE);

    return [
      EcaElementType::Event->getPlural() => empty($filterIds) ? $this->getEvents() : array_values(array_filter($this->getEvents(), $filterFunction)),
      EcaElementType::Condition->getPlural() => empty($filterIds) ? $this->getConditions() : array_values(array_filter($this->getConditions(), $filterFunction)),
      EcaElementType::Action->getPlural() => empty($filterIds) ? $this->getActions() : array_values(array_filter($this->getActions(), $filterFunction)),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getModels(array $filterIds = []): array {
    /** @var \Drupal\eca\Entity\EcaStorage $storage */
    $storage = $this->entityTypeManager->getStorage('eca');
    /** @var \Drupal\eca\Entity\Eca[] $models */
    $models = $storage->loadMultiple();

    if (!empty($filterIds)) {
      $models = array_filter($models, function (Eca $model) use ($filterIds) {
        return in_array($model->id(), $filterIds, TRUE);
      });
    }

    return array_reduce($models, function (array $carry, Eca $eca) {
      $model = $this->modelMapper->fromEntity($eca);
      $data = array_filter($this->modelMapper->toArray($model));

      if ($this->viewMode === DataViewModeEnum::Teaser) {
        $data = array_intersect_key($data, array_flip(['model_id', 'label', 'description']));
      }

      $carry[] = $data;

      return $carry;
    }, []);
  }

  /**
   * {@inheritdoc}
   */
  public function setViewMode(DataViewModeEnum $viewMode): DataProviderInterface {
    $this->viewMode = $viewMode;

    return $this;
  }

  /**
   * Converts a list of plugins to an array of essential information.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface[] $plugins
   *   The list of plugins.
   *
   * @return array
   *   Returns a converted list of plugins with essential information.
   */
  protected function convertPlugins(array $plugins): array {
    $output = [];

    foreach ($plugins as $plugin) {
      $info = [
        'plugin_id' => $plugin->getPluginId(),
        'name' => (string) $plugin->getPluginDefinition()['label'],
      ];

      if ($this->viewMode === DataViewModeEnum::Full) {
        $info['configuration'] = $this->buildConfig($plugin);
      }

      if (!empty($plugin->getPluginDefinition()['description'])) {
        $info['description'] = (string) $plugin->getPluginDefinition()['description'];
      }

      $output[] = array_filter($info);
    }

    return $output;
  }

  /**
   * Build the configuration details for the given plugin.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The plugin.
   *
   * @return array
   *   Returns the configuration details.
   */
  protected function buildConfig(PluginInspectionInterface $plugin): array {
    if (!$plugin instanceof ConfigurableInterface && !$plugin instanceof PluginFormInterface) {
      return [];
    }

    $defaultConfig = $plugin->defaultConfiguration();
    $configKeys = array_keys($defaultConfig);
    $elements = array_filter($plugin->buildConfigurationForm([], new FormState()), function ($key) use ($configKeys) {
      return in_array($key, $configKeys);
    }, ARRAY_FILTER_USE_KEY);

    return array_reduce(array_keys($elements), function (array $carry, $key) use ($elements, $defaultConfig) {
      $config = [
        'config_id' => $key,
        'name' => (string) $elements[$key]['#title'],
      ];
      if (!empty($elements[$key]['#description'])) {
        $config['description'] = (string) $elements[$key]['#description'];
      }
      if (!empty($elements[$key]['#options'])) {
        $config['options'] = array_keys($elements[$key]['#options']);
      }
      if (isset($defaultConfig[$key])) {
        $config['value_type'] = gettype($defaultConfig[$key]);
      }

      $carry[] = $config;

      return $carry;
    }, []);
  }

}
