<?php

namespace Drupal\ai_integration_eca_agents\Services\ModelMapper;

use Drupal\ai_integration_eca_agents\EcaElementType;
use Drupal\ai_integration_eca_agents\Plugin\DataType\EcaModel;
use Drupal\ai_integration_eca_agents\TypedData\EcaGatewayDefinition;
use Drupal\ai_integration_eca_agents\TypedData\EcaModelDefinition;
use Drupal\ai_integration_eca_agents\TypedData\EcaPluginDefinition;
use Drupal\ai_integration_eca_agents\TypedData\EcaSuccessorDefinition;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\Exception\MissingDataException;
use Drupal\Core\TypedData\ListInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\eca\Entity\Eca;
use Drupal\eca\Entity\Objects\EcaEvent;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * The model mapper.
 */
class ModelMapper implements ModelMapperInterface {

  /**
   * The model mapper.
   *
   * @param \Drupal\Core\TypedData\TypedDataManagerInterface $typedDataManager
   *   The typed data manager.
   */
  public function __construct(
    protected TypedDataManagerInterface $typedDataManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function fromPayload(array $payload): EcaModel {
    $definition = EcaModelDefinition::create();
    /** @var \Drupal\ai_integration_eca_agents\Plugin\DataType\EcaModel $model */
    $model = $this->typedDataManager->create($definition, $payload);

    /** @var \Symfony\Component\Validator\ConstraintViolationList $violations */
    $violations = $model->validate();
    if (count($violations) > 0) {
      throw new MissingDataException($this->formatValidationMessages($model, $violations));
    }

    return $model;
  }

  /**
   * {@inheritdoc}
   */
  public function fromEntity(Eca $entity): EcaModel {
    $modelDef = EcaModelDefinition::create();
    /** @var \Drupal\ai_integration_eca_agents\Plugin\DataType\EcaModel $model */
    $model = $this->typedDataManager->create($modelDef);

    // Basic properties.
    $model->set('model_id', $entity->id());
    $model->set('label', $entity->label());
    $documentation = $entity->getThirdPartySetting('modeler_api', 'documentation');
    if (!empty($documentation)) {
      $model->set('documentation', $documentation);
    }

    // Events.
    $plugins = array_reduce($entity->getUsedEvents(), function (array $carry, EcaEvent $event) {
      $successors = $this->mapSuccessorsToModel($event->getSuccessors());

      $def = EcaPluginDefinition::create();
      $def->setSetting('data_type', EcaElementType::Event);
      /** @var \Drupal\ai_integration_eca_agents\Plugin\DataType\EcaPlugin $model */
      $model = $this->typedDataManager->create($def);
      $model->set('element_id', $event->getId());
      $model->set('plugin_id', $event->getPlugin()->getPluginId());
      $model->set('label', $event->getLabel());
      $model->set('configuration', $event->getConfiguration());
      $model->set('successors', $successors);

      $carry[] = $this->toArray($model);

      return $carry;
    }, []);
    $model->set('events', $plugins);

    // Conditions.
    $conditions = $entity->getConditions();
    $plugins = array_reduce(array_keys($conditions), function (array $carry, string $conditionId) use ($conditions) {
      $def = EcaPluginDefinition::create();
      $def->setSetting('data_type', EcaElementType::Condition);
      /** @var \Drupal\ai_integration_eca_agents\Plugin\DataType\EcaPlugin $model */
      $model = $this->typedDataManager->create($def);
      $model->set('element_id', $conditionId);
      $model->set('plugin_id', $conditions[$conditionId]['plugin']);
      $model->set('configuration', $conditions[$conditionId]['configuration']);

      $carry[] = $this->toArray($model);

      return $carry;
    }, []);
    $model->set('conditions', $plugins);

    // Actions.
    $actions = $entity->getActions();
    $plugins = array_reduce(array_keys($actions), function (array $carry, string $actionId) use ($actions) {
      $successors = $this->mapSuccessorsToModel($actions[$actionId]['successors']);

      $def = EcaPluginDefinition::create();
      $def->setSetting('data_type', EcaElementType::Action);
      /** @var \Drupal\ai_integration_eca_agents\Plugin\DataType\EcaPlugin $model */
      $model = $this->typedDataManager->create($def);
      $model->set('element_id', $actionId);
      $model->set('plugin_id', $actions[$actionId]['plugin']);
      $model->set('label', $actions[$actionId]['label']);
      $model->set('configuration', $actions[$actionId]['configuration']);
      $model->set('successors', $successors);

      $carry[] = $this->toArray($model);

      return $carry;
    }, []);
    $model->set('actions', $plugins);

    // Gateways.
    $gateways = $entity->get('gateways');
    $plugins = array_reduce(array_keys($gateways), function (array $carry, string $gatewayId) use ($gateways) {
      $successors = $this->mapSuccessorsToModel($gateways[$gatewayId]['successors']);

      /** @var \Drupal\ai_integration_eca_agents\Plugin\DataType\EcaPlugin $model */
      $model = $this->typedDataManager->create(EcaGatewayDefinition::create());
      $model->set('gateway_id', $gatewayId);
      $model->set('type', $gateways[$gatewayId]['type']);
      $model->set('successors', $successors);

      $carry[] = $this->toArray($model);

      return $carry;
    }, []);
    $model->set('gateways', $plugins);

    return $model;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(TypedDataInterface $data): mixed {
    if ($data instanceof ListInterface) {
      $values = [];
      foreach ($data as $item) {
        $values[] = $this->toArray($item);
      }

      return $values;
    }

    if ($data instanceof ComplexDataInterface) {
      $values = [];
      foreach ($data->getProperties(TRUE) as $name => $property) {
        $values[$name] = $this->toArray($property);
      }

      return $values;
    }

    return $data->getValue();
  }

  /**
   * Format the violation messages in a human-readable way.
   *
   * @param \Drupal\Core\TypedData\ComplexDataInterface $data
   *   The typed data object.
   * @param \Symfony\Component\Validator\ConstraintViolationListInterface $violations
   *   The violation list.
   *
   * @return string
   *   Returns the formatted violation messages.
   */
  protected function formatValidationMessages(ComplexDataInterface $data, ConstraintViolationListInterface $violations): string {
    $lines = [
      sprintf('There were validation errors in %s:', $data->getName()),
    ];
    /** @var \Symfony\Component\Validator\ConstraintViolationInterface $violation */
    foreach ($violations as $violation) {
      $message = sprintf('- %s', $violation->getMessage());
      if (!empty($violation->getPropertyPath())) {
        $message = sprintf('- %s: %s', $violation->getPropertyPath(), $violation->getMessage());
      }
      $lines[] = $message;
    }

    return implode("\n", $lines);
  }

  /**
   * Map an array of successor-data to the EcaSuccessor data type.
   *
   * @param array $successors
   *   The array of successor-data.
   *
   * @return array
   *   Returns a normalized version of the EcaSuccessor data type.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
   */
  protected function mapSuccessorsToModel(array $successors): array {
    return array_filter(array_reduce($successors, function ($carry, array $successor) {
      /** @var \Drupal\ai_integration_eca_agents\Plugin\DataType\EcaSuccessor $model */
      $model = $this->typedDataManager->create(EcaSuccessorDefinition::create());
      $model->set('element_id', $successor['id']);
      $model->set('condition', $successor['condition']);

      $carry[] = array_filter($this->toArray($model), function ($value) {
        return $value !== NULL;
      });

      return $carry;
    }, []));
  }

}
