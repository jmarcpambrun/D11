<?php

namespace Drupal\ai_integration_eca_agents\Services\EcaRepository;

use Drupal\ai_integration_eca_agents\EntityViolationException;
use Drupal\ai_integration_eca_agents\MissingEventException;
use Drupal\ai_integration_eca_agents\Services\ModelMapper\ModelMapperInterface;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Utility\Random;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\Exception\MissingDataException;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\eca\Entity\Eca;

/**
 * Repository for the ECA entity type.
 */
class EcaRepository implements EcaRepositoryInterface {

  /**
   * Constructs an EcaHelper-instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\ai_integration_eca_agents\Services\ModelMapper\ModelMapperInterface $modelMapper
   *   The model mapper.
   * @param \Drupal\Core\TypedData\TypedDataManagerInterface $typedDataManager
   *   The typed data manager.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ModelMapperInterface $modelMapper,
    protected TypedDataManagerInterface $typedDataManager,
    protected LoggerChannelInterface $logger,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function get(string $id): ?Eca {
    $eca = $this->entityTypeManager->getStorage('eca')->load($id);
    return $eca instanceof Eca ? $eca : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function build(array $data, bool $save = TRUE, ?string $id = NULL): Eca {
    /** @var \Drupal\eca\Entity\EcaStorage $storage */
    $storage = $this->entityTypeManager->getStorage('eca');
    /** @var \Drupal\eca\Entity\Eca $eca */
    $eca = $storage->create();
    if (!empty($id)) {
      /** @var \Drupal\eca\Entity\Eca $eca */
      $eca = $storage->load($id);
    }

    // Convert the given data to the ECA-model.
    $model = $this->modelMapper->fromPayload($data);

    // Map the model to the entity.
    $random = new Random();
    $idFallback = $model->get('model_id')->getString();
    if (empty($idFallback)) {
      $idFallback = sprintf('process_%s', $random->name(7));
    }
    $eca->set('id', $id ?? $idFallback);
    $version = $model->get('version')->getString();
    if ($version === '') {
      $version = '0.0.1';
    }
    $eca->setThirdPartySetting('modeler_api', 'label', $model->get('label')->getString());
    $eca->setThirdPartySetting('modeler_api', 'version', $version);
    $eca->setStatus(FALSE);
    $eca->resetComponents();

    // Set events.
    $events = $model->get('events');
    if (is_iterable($events)) {
      /** @var \Drupal\ai_integration_eca_agents\Plugin\DataType\EcaPlugin $plugin */
      foreach ($events as $plugin) {
        $successors = $plugin->get('successors')->getValue() ?? [];
        foreach ($successors as &$successor) {
          $successor['id'] = $successor['element_id'];
          $successor['condition'] ??= '';
          unset($successor['element_id']);
        }

        $eca->addEvent(
          $plugin->get('element_id')->getString(),
          $plugin->get('plugin_id')->getString(),
          $plugin->get('label')->getString(),
          $this->getConfiguration($plugin),
          $successors
        );
      }
    }

    // Set conditions.
    $conditions = $model->get('conditions');
    if (is_iterable($conditions)) {
      /** @var \Drupal\ai_integration_eca_agents\Plugin\DataType\EcaPlugin $plugin */
      foreach ($conditions as $plugin) {
        $eca->addCondition(
          $plugin->get('element_id')->getString(),
          $plugin->get('plugin_id')->getString(),
          // @todo $plugin->get('label')->getString().
          '',
          $this->getConfiguration($plugin),
        );
      }
    }

    // Set gateways.
    $gateways = $model->get('gateways');
    if (is_iterable($gateways)) {
      /** @var \Drupal\ai_integration_eca_agents\Plugin\DataType\EcaGateway $plugin */
      foreach ($gateways as $plugin) {
        $successors = $plugin->get('successors')->getValue() ?? [];
        foreach ($successors as &$successor) {
          $successor['id'] = $successor['element_id'];
          $successor['condition'] ??= '';
          unset($successor['element_id']);
        }

        $eca->addGateway(
          $plugin->get('gateway_id')->getString(),
          $plugin->get('type')->getValue(),
          $successors
        );
      }
    }

    // Set actions.
    $actions = $model->get('actions');
    if (is_iterable($actions)) {
      /** @var \Drupal\ai_integration_eca_agents\Plugin\DataType\EcaGateway $plugin */
      foreach ($actions as $plugin) {
        $successors = $plugin->get('successors')->getValue() ?? [];
        foreach ($successors as &$successor) {
          $successor['id'] = $successor['element_id'];
          $successor['condition'] ??= '';
          unset($successor['element_id']);
        }

        $eca->addAction(
          $plugin->get('element_id')->getString(),
          $plugin->get('plugin_id')->getString(),
          $plugin->get('label')->getString(),
          $this->getConfiguration($plugin),
          $successors
        );
      }
    }

    // Validate the entity. Typed data has to derive a definition for a config
    // entity type to do that, which is not something every combination of core
    // and ECA supports. When it refuses, say so and carry on: the payload was
    // already validated against the model definition above, which is where the
    // plugin ids, the required properties and the successor wiring are
    // checked. Losing this last pass is not worth taking down the caller for.
    try {
      $definition = $this->typedDataManager->createDataDefinition(sprintf('entity:%s', $eca->getEntityTypeId()));
      $violations = $this->typedDataManager->create($definition, $eca)->validate();
    }
    catch (\LogicException | PluginNotFoundException $e) {
      $violations = NULL;
      $this->logger->warning('The ECA model %id could not be validated as an entity, only the model definition was checked: @message', [
        '%id' => $eca->id(),
        '@message' => $e->getMessage(),
      ]);
    }
    if ($violations !== NULL && $violations->count()) {
      throw new EntityViolationException('', 0, NULL, $violations);
    }
    if (empty($eca->getUsedEvents())) {
      throw new MissingEventException('No events registered.');
    }

    // Save the entity.
    if ($save) {
      $eca->save();
    }

    return $eca;
  }

  /**
   * Read the configuration of an element as an array.
   *
   * The configuration is typed as "any", so anything survives validation. An
   * LLM regularly hands in a JSON encoded string instead of an object, which
   * would end in a TypeError deeper down. Decode what can be decoded and
   * report the rest back, so that the caller can feed it to the LLM.
   *
   * @param \Drupal\Core\TypedData\ComplexDataInterface $element
   *   The event, condition, gateway or action.
   *
   * @return array
   *   Returns the configuration.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function getConfiguration(ComplexDataInterface $element): array {
    $configuration = $element->get('configuration')->getValue();

    if ($configuration === NULL || $configuration === '') {
      return [];
    }
    if (is_array($configuration)) {
      return $configuration;
    }
    if (is_string($configuration)) {
      $decoded = json_decode($configuration, TRUE);
      if (is_array($decoded)) {
        return $decoded;
      }
    }

    throw new MissingDataException(sprintf(
      'The configuration of "%s" has to be an object with the configuration keys of the plugin, %s given.',
      $element->get('element_id')->getString(),
      gettype($configuration)
    ));
  }

}
