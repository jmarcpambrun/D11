<?php

namespace Drupal\modeler\Plugin\ModelerApiModeler;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\modeler\FormToJsonConverter;
use Drupal\modeler_api\Api;
use Drupal\modeler_api\Attribute\Modeler;
use Drupal\modeler_api\Component;
use Drupal\modeler_api\ComponentSuccessor;
use Drupal\modeler_api\Form\Wrapper;
use Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerInterface;
use Drupal\modeler_api\Plugin\ModelerApiModeler\ModelerBase;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Plugin implementation of the React Flow Modeler.
 */
#[Modeler(
  id: "workflow_modeler",
  label: new TranslatableMarkup("Workflow modeler"),
  description: new TranslatableMarkup("Workflow modeler built with React Flow.")
)]
class WorkflowModeler extends ModelerBase {

  /**
   * The raw model data as JSON string.
   */
  protected string $rawData = '';

  /**
   * Parsed model components.
   */
  protected array $parsedComponents = [];

  /**
   * Parsed model metadata.
   */
  protected array $metadata = [];

  /**
   * The form-to-JSON converter service.
   */
  protected ?FormToJsonConverter $formToJsonConverter = NULL;

  /**
   * Get the form-to-JSON converter service.
   */
  protected function getFormToJsonConverter(): FormToJsonConverter {
    if ($this->formToJsonConverter === NULL) {
      // @phpstan-ignore-next-line
      $this->formToJsonConverter = \Drupal::service('modeler.form_to_json_converter');
    }
    return $this->formToJsonConverter;
  }

  /**
   * {@inheritdoc}
   */
  public function getRawFileExtension(): ?string {
    return 'json';
  }

  /**
   * {@inheritdoc}
   */
  public function isEditable(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function convert(ModelOwnerInterface $owner, ConfigEntityInterface $model, bool $readOnly = FALSE): array {
    if ($model->isNew()) {
      $id = '';
      $modelData = $this->prepareEmptyModelData($id);
    }
    else {
      $id = $model->id();
      // Rebuild model data from the model entity.
      $usedComponents = $owner->getUsedComponents($model);

      // Build nodes array and collect conditions and annotations.
      $nodes = [];
      $edges = [];
      $conditions = [];
      $annotations = [];

      foreach ($owner->getAnnotations($model) as $annotation) {
        foreach ($annotation->getSuccessors() as $association) {
          $annotations[$association->getConditionId()] = $annotation->getLabel();
        }
      }

      // First pass: collect all condition components so they are available
      // when building edges. Without this, conditions referenced by events
      // (which are iterated before conditions) would be missing.
      foreach ($usedComponents as $component) {
        if ($component->getType() === Api::COMPONENT_TYPE_LINK) {
          $componentId = $component->getId();
          $element = [
            'id' => $componentId,
            'plugin' => $component->getPluginId(),
            'label' => $component->getLabel(),
            'position' => ['x' => 100, 'y' => 100],
            'configuration' => $component->getConfiguration(),
            'componentType' => Api::COMPONENT_TYPE_LINK,
            'configured' => TRUE,
          ];
          if (isset($annotations[$componentId])) {
            $element['annotation'] = $annotations[$componentId];
          }
          $conditions[$componentId] = $element;
        }
      }

      // Second pass: build nodes and edges with full condition data available.
      foreach ($usedComponents as $component) {
        $componentId = $component->getId();
        $componentType = $component->getType();

        // Skip conditions — already collected in the first pass.
        if ($componentType === Api::COMPONENT_TYPE_LINK) {
          continue;
        }

        $element = [
          'id' => $componentId,
          'plugin' => $component->getPluginId(),
          'label' => $component->getLabel(),
          'position' => ['x' => 100, 'y' => 100],
          'configuration' => $component->getConfiguration(),
          'componentType' => $componentType,
          'configured' => TRUE,
        ];
        if (isset($annotations[$componentId])) {
          $element['annotation'] = $annotations[$componentId];
        }
        $nodes[] = $element;

        // Create edges from successors.
        $successors = $component->getSuccessors();
        foreach ($successors as $successor) {
          $successorId = $successor->getId();
          $conditionId = $successor->getConditionId();

          $edge = [
            'id' => $componentId . '_' . $successorId,
            'source' => $componentId,
            'target' => $successorId,
          ];

          // Attach condition data if there's a condition.
          if ($conditionId && isset($conditions[$conditionId])) {
            $edge['condition'] = $conditions[$conditionId]['plugin'];
            $edge['conditionConfiguration'] = $conditions[$conditionId]['configuration'];
            $edge['conditionLabel'] = $conditions[$conditionId]['label'];
            // Preserve the original condition ID for round-trip stability.
            $edge['conditionId'] = $conditionId;
          }

          // Add annotation if one exists for this edge.
          $edgeId = $edge['id'];
          if (isset($annotations[$edgeId])) {
            $edge['annotation'] = $annotations[$edgeId];
          }

          $edges[] = $edge;
        }
      }

      $modelData = json_encode([
        'nodes' => $nodes,
        'edges' => $edges,
      ]);
    }
    return $this->edit($owner, $id, $modelData, $model->isNew(), $readOnly);
  }

  /**
   * {@inheritdoc}
   */
  public function edit(ModelOwnerInterface $owner, string $id, string $data, bool $isNew = FALSE, bool $readOnly = FALSE): array {
    $components = [];
    if (!$readOnly) {
      foreach ($owner->supportedOwnerComponentTypes() as $type => $typeName) {
        foreach ($owner->availableOwnerComponents($type) as $plugin) {
          $definition = $plugin->getPluginDefinition();
          $components[] = [
            'plugin' => $plugin->getPluginId(),
            'provider' => $definition['provider'],
            'label' => $definition['label'] ?? $plugin->getPluginId(),
            'description' => $definition['description'] ?? '',
            'componentType' => $type,
            'documentationUrl' => $owner->pluginDocUrl($plugin, $typeName),
          ];
        }
      }
    }
    $settings = [
      'modelId' => $id,
      'modelData' => $data,
      'components' => $components,
      'typeMap' => Api::COMPONENT_TYPE_NAMES,
    ];
    if ($this->request->query->has('select')) {
      $settings['selectComponentId'] = $this->request->query->get('select');
    }
    if (!$readOnly && $this->request->query->has('context')) {
      $settings['selectContextId'] = $this->request->query->get('context');
    }
    if (!$readOnly && $this->request->query->has('contextConfig')) {
      $settings['setContextConfig'] = json_decode($this->request->query->get('contextConfig'), TRUE);
    }
    if ($this->request->query->has('hash')) {
      $settings['replayData'] = $owner->getReplayData($this->request->query->get('hash'));
    }
    if ($this->request->headers->has('HX-Request')) {
      $settings['stayInContextOnClose'] = TRUE;
    }
    return [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'workflow-modeler-wrapper',
        'class' => ['workflow-modeler-wrapper'],
      ],
      '#attached' => [
        'library' => ['modeler/react-ui'],
        'drupalSettings' => [
          'modeler' => $settings,
        ],
      ],
      'content' => [
        '#markup' => '<div class="modeler-loading-overlay">
          <div class="modeler-loading-content">
            <div class="modeler-loading-text">Modeler Loading...</div>
          </div>
        </div>',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function parseData(ModelOwnerInterface $owner, string $data): void {
    $this->rawData = $data;
    $this->parsedComponents = [];
    $this->metadata = [];

    // If we have JSON data, parse it and extract components and metadata.
    if (!empty($data)) {
      $decoded = json_decode($data, TRUE);
      if (is_array($decoded)) {
        // Extract metadata.
        if (isset($decoded['metadata'])) {
          $this->metadata = $decoded['metadata'];
          // Ensure ID is also available from the root level.
          if (isset($decoded['id'])) {
            $this->metadata['id'] = $decoded['id'];
          }
        }
        elseif (isset($decoded['id'])) {
          // Fallback: extract metadata from root level for backward
          // compatibility.
          $this->metadata = [
            'id' => $decoded['id'],
            'version' => $decoded['version'] ?? '1.0.0',
            'label' => $decoded['label'] ?? 'Untitled Model',
            'description' => $decoded['description'] ?? '',
            'executable' => $decoded['executable'] ?? TRUE,
            'template' => $decoded['template'] ?? FALSE,
            'storage' => $decoded['storage'] ?? '',
            'documentation' => $decoded['documentation'] ?? '',
            'tags' => $decoded['tags'] ?? [],
            'changelog' => $decoded['changelog'] ?? '',
          ];
        }

        // First, collect all condition components from edges to create them.
        $conditionComponents = [];
        if (isset($decoded['edges'])) {
          foreach ($decoded['edges'] as $edge) {
            if (!empty($edge['condition'])) {
              foreach ($edge['conditionConfiguration'] ?? [] as $key => $value) {
                if (is_bool($value) && $value === FALSE) {
                  unset($edge['conditionConfiguration'][$key]);
                }
              }
              // Preserve the original condition ID if provided, otherwise
              // generate a new one from the edge ID.
              $conditionId = !empty($edge['conditionId'])
                ? $edge['conditionId']
                : $edge['id'] . '_condition';
              $conditionComponents[$conditionId] = new Component(
                $owner,
                $conditionId,
                Api::COMPONENT_TYPE_LINK,
                $edge['condition'],
                $edge['conditionLabel'] ?? $edge['condition'],
                $edge['conditionConfiguration'] ?? [],
              );
            }
          }
        }

        // Create regular node components with their successors.
        $nodeComponents = [];
        if (isset($decoded['nodes'])) {
          foreach ($decoded['nodes'] as $node) {
            // Determine the integer component type: prefer the explicit
            // `componentType` sent by the current frontend, fall back to
            // resolving the legacy string `type` via COMPONENT_TYPE_NAMES.
            $componentType = $node['componentType']
              ?? array_search($node['type'] ?? '', Api::COMPONENT_TYPE_NAMES, TRUE)
              ?: Api::COMPONENT_TYPE_ELEMENT;
            if (isset($node['plugin'])) {
              foreach ($node['configuration'] ?? [] as $key => $value) {
                if (is_bool($value) && $value === FALSE) {
                  unset($node['configuration'][$key]);
                }
              }
              $nodeId = $node['id'] ?? uniqid('component_');
              $nodeComponents[$nodeId] = new Component(
                $owner,
                $nodeId,
                $componentType,
                $node['plugin'],
                $node['label'] ?? $node['plugin'],
                $node['configuration'] ?? [],
              );
            }
          }
        }

        // Build successors from edges.
        if (isset($decoded['edges'])) {
          foreach ($decoded['edges'] as $edge) {
            $sourceId = $edge['source'];
            $targetId = $edge['target'];
            $conditionId = '';

            // If there's a condition, use the original condition ID if
            // provided, otherwise generate one from the edge ID.
            if (!empty($edge['condition'])) {
              $conditionId = !empty($edge['conditionId'])
                ? $edge['conditionId']
                : $edge['id'] . '_condition';
            }

            // Add successor to the source component.
            if (isset($nodeComponents[$sourceId])) {
              $successors = $nodeComponents[$sourceId]->getSuccessors();
              $successors[] = new ComponentSuccessor($targetId, $conditionId);

              // Create new component with updated successors.
              $nodeComponents[$sourceId] = new Component(
                $owner,
                $sourceId,
                $nodeComponents[$sourceId]->getType(),
                $nodeComponents[$sourceId]->getPluginId(),
                $nodeComponents[$sourceId]->getLabel(),
                $nodeComponents[$sourceId]->getConfiguration(),
                $successors,
              );
            }
          }
        }

        // Create annotation components for nodes and edges with annotations.
        $annotationComponents = [];

        // Node annotations.
        if (isset($decoded['nodes'])) {
          foreach ($decoded['nodes'] as $node) {
            if (!empty($node['annotation'])) {
              $annotationId = 'annotation_' . $node['id'];
              $associationId = 'association_' . $node['id'];
              $annotationComponents[$annotationId] = new Component(
                $owner,
                $annotationId,
                Api::COMPONENT_TYPE_ANNOTATION,
                '',
                $node['annotation'],
                [],
                [new ComponentSuccessor($associationId, $node['id'])],
              );
            }
          }
        }

        // Edge annotations.
        if (isset($decoded['edges'])) {
          foreach ($decoded['edges'] as $edge) {
            if (!empty($edge['annotation'])) {
              $annotationId = 'annotation_' . $edge['id'];
              $associationId = 'association_' . $edge['id'];
              $annotationComponents[$annotationId] = new Component(
                $owner,
                $annotationId,
                Api::COMPONENT_TYPE_ANNOTATION,
                '',
                $edge['annotation'],
                [],
                [new ComponentSuccessor($associationId, $edge['id'])],
              );
            }
          }
        }

        // Combine all components.
        $this->parsedComponents = array_merge(
          array_values($nodeComponents),
          array_values($conditionComponents),
          array_values($annotationComponents)
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getRawData(): string {
    return $this->rawData;
  }

  /**
   * {@inheritdoc}
   */
  public function readComponents(): array {
    return $this->parsedComponents;
  }

  /**
   * {@inheritdoc}
   */
  public function configForm(ModelOwnerInterface $owner): JsonResponse {
    $data = [];

    // Get JSON data from request body.
    $json_data = json_decode($this->request->getContent(), TRUE);
    if (!$json_data) {
      $data['error'] = $this->t('Invalid data.');
    }
    else {
      $component_type = $json_data['component_type'] ?? NULL;
      $model_id = $json_data['model_id'] ?? NULL;
      $model_is_new = $json_data['is_new'] ?? FALSE;
      $plugin_id = $json_data['plugin_id'] ?? NULL;
      $configuration = $json_data['configuration'] ?? [];
      if (!$component_type || !$plugin_id) {
        $data['error'] = $this->t('Invalid JSON data.');
      }
      else {
        $plugin = $owner->ownerComponent($component_type, $plugin_id);
        if (!$plugin) {
          $data['error'] = $this->t('Invalid plugin.');
        }
        elseif (!$owner->ownerComponentEditable($plugin)) {
          $data['error'] = $this->t('Plugin not editable.');
        }
        else {
          if ($plugin instanceof ConfigurableInterface) {
            $plugin->setConfiguration($configuration);
          }
          $form = $owner->buildConfigurationForm($plugin, $model_id, $model_is_new);
          // The model owner may have added configuration properties to the
          // plugin although it's not configurable. This e.g. happens in ECA for
          // action plugins that have a type property. Let's add the default
          // values separately for those.
          if (!($plugin instanceof ConfigurableInterface)) {
            foreach ($configuration as $configKey => $configValue) {
              if (isset($form[$configKey])) {
                $form[$configKey]['#default_value'] = $configValue;
              }
            }
          }
          $form = $this->formBuilder->getForm(Wrapper::class, $form);
          unset($form['ecatemplatedummy']);
          // Derive the config schema key for this plugin to enable YAML
          // schema discovery on textarea fields.
          $schema_key = $owner->getPluginSchemaKey($plugin);
          // Convert form to JSON-serializable format.
          $data['form'] = $this->getFormToJsonConverter()->convert($form, $schema_key);
        }
      }
    }
    return new JsonResponse($data);
  }

  /**
   * {@inheritdoc}
   */
  public function prepareEmptyModelData(string &$id): string {
    $id = $this->generateId();

    $emptyModel = [
      'id' => $id,
      'metadata' => [
        'version' => '1.0.0',
        'label' => 'New Model',
        'documentation' => '',
        'executable' => TRUE,
        'template' => FALSE,
        'storage' => '',
        'tags' => [],
        'changelog' => '',
      ],
      'nodes' => [],
      'edges' => [],
    ];

    return json_encode($emptyModel, JSON_PRETTY_PRINT);
  }

  /**
   * {@inheritdoc}
   */
  public function getId(): string {
    return $this->metadata['id'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return $this->metadata['label'] ?? 'Untitled Model';
  }

  /**
   * {@inheritdoc}
   */
  public function getTags(): array {
    $tags = $this->metadata['tags'] ?? [];
    return is_array($tags) ? $tags : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getChangelog(): string {
    return $this->metadata['changelog'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getTemplate(): bool {
    return (bool) ($this->metadata['template'] ?? FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function getStorage(): string {
    return $this->metadata['storage'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getDocumentation(): string {
    return $this->metadata['documentation'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus(): bool {
    return (bool) ($this->metadata['executable'] ?? TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getVersion(): string {
    return $this->metadata['version'] ?? '1.0.0';
  }

  /**
   * {@inheritdoc}
   */
  public function enable(ModelOwnerInterface $owner): static {
    $this->metadata['executable'] = TRUE;
    $this->rebuildRawData();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function disable(ModelOwnerInterface $owner): static {
    $this->metadata['executable'] = FALSE;
    $this->rebuildRawData();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function clone(ModelOwnerInterface $owner, string $id, string $label): static {
    $this->metadata['id'] = $id;
    $this->metadata['label'] = $label;
    $this->rebuildRawData();
    return $this;
  }

  /**
   * Rebuilds the raw JSON data from the current metadata.
   *
   * Decodes the existing raw data, applies the current metadata and ID,
   * and re-encodes the result. This ensures rawData stays in sync with
   * metadata after any modification.
   */
  protected function rebuildRawData(): void {
    $decoded = json_decode($this->rawData, TRUE);
    if (!is_array($decoded)) {
      $decoded = [];
    }
    $decoded['id'] = $this->metadata['id'] ?? '';
    $decoded['metadata'] = $this->metadata;
    unset($decoded['metadata']['id']);
    $this->rawData = json_encode($decoded, JSON_PRETTY_PRINT);
  }

}
