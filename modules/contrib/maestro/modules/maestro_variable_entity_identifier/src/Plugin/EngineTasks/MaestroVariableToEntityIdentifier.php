<?php

namespace Drupal\maestro_variable_entity_identifier\Plugin\EngineTasks;

use Drupal\Core\Annotation\Translation;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\maestro\Form\MaestroExecuteInteractive;
use Drupal\maestro\MaestroEngineTaskInterface;
use Drupal\maestro\MaestroTaskTrait;

/**
 * Maestro task that writes a process variable value as a Maestro entity identifier.
 *
 * @Plugin(
 *   id = "MaestroVariableToEntityIdentifier",
 *   task_description = @Translation("Converts a process variable value into a Maestro entity identifier."),
 * )
 */
class MaestroVariableToEntityIdentifier extends PluginBase implements MaestroEngineTaskInterface {

  use MaestroTaskTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct($configuration = NULL) {
    if (is_array($configuration)) {
      $this->processID = $configuration[0];
      $this->queueID = $configuration[1];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isInteractive() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function shortDescription() {
    return $this->t('Variable to Entity Identifier');
  }

  /**
   * {@inheritdoc}
   */
  public function description() {
    return $this->t('Reads a process variable value and writes it as a Maestro entity identifier.');
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    return 'MaestroVariableToEntityIdentifier';
  }

  /**
   * {@inheritdoc}
   */
  public function getTaskColours() {
    return '#daa520';
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $templateMachineName = MaestroEngine::getTemplateIdFromProcessId($this->processID);
    $taskMachineName = MaestroEngine::getTaskIdFromQueueId($this->queueID);
    $task = MaestroEngine::getTemplateTaskByID($templateMachineName, $taskMachineName);

    $data = $task['data']['vtei'] ?? [];
    $process_variable = $data['process_variable'] ?? '';
    $unique_id = $data['unique_id'] ?? '';
    $entity_type = $data['entity_type'] ?? '';

    if (empty($process_variable) || empty($unique_id) || empty($entity_type)) {
      \Drupal::logger('maestro_variable_entity_identifier')->warning(
        'Task @task is not properly configured (missing process_variable, unique_id, or entity_type).',
        ['@task' => $taskMachineName]
      );
      return TRUE;
    }

    $entity_id = MaestroEngine::getProcessVariable($process_variable, $this->processID);

    if (empty($entity_id)) {
      \Drupal::logger('maestro_variable_entity_identifier')->warning(
        'Process variable "@var" is empty for process @pid; entity identifier not written.',
        ['@var' => $process_variable, '@pid' => $this->processID]
      );
      return TRUE;
    }

    // Derive the bundle by loading the entity; fall back to entity_type.
    $bundle = $entity_type;
    $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id);
    if ($entity) {
      $bundle = $entity->bundle();
    }

    MaestroEngine::createEntityIdentifier($this->processID, $entity_type, $bundle, $unique_id, $entity_id);

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getExecutableForm($modal, MaestroExecuteInteractive $parent) {}

  /**
   * {@inheritdoc}
   */
  public function handleExecuteSubmit(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function getTaskEditForm(array $task, $templateMachineName) {
    $data = $task['data']['vtei'] ?? [];

    $form['vtei'] = [
      '#type' => 'fieldset',
      '#tree' => TRUE,
      '#title' => $this->t('Variable to Entity Identifier Settings'),
      '#collapsed' => FALSE,
    ];

    $form['vtei']['information'] = [
      '#markup' => 
        '<div class="messages">' .
        $this->t('The variable selected in this interface will have its value converted to a Maestro Entity Identifier upon execution.') .
        '</div>'
    ];

    // Process variable dropdown.
    $variables = MaestroEngine::getTemplateVariables($templateMachineName);
    $variable_options = [];
    foreach ($variables as $name => $info) {
      $variable_options[$name] = $name;
    }

    $form['vtei']['process_variable'] = [
      '#type' => 'select',
      '#title' => $this->t('Process variable'),
      '#description' => $this->t('The process variable whose value holds the entity ID.'),
      '#options' => $variable_options,
      '#required' => TRUE,
      '#default_value' => $data['process_variable'] ?? '',
    ];

    $form['vtei']['unique_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Unique ID'),
      '#description' => $this->t('The entity identifier key (unique_id) used by other tasks in this workflow to reference the entity.'),
      '#required' => TRUE,
      '#default_value' => $data['unique_id'] ?? '',
    ];

    // Entity type dropdown content entity types only.
    $entity_type_options = [];
    $definitions = \Drupal::entityTypeManager()->getDefinitions();
    foreach ($definitions as $id => $definition) {
      if ($definition instanceof ContentEntityTypeInterface) {
        $entity_type_options[$id] = (string) $definition->getLabel();
      }
    }
    asort($entity_type_options);

    $form['vtei']['entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity type'),
      '#description' => $this->t('The type of entity that the process variable ID refers to.'),
      '#options' => $entity_type_options,
      '#required' => TRUE,
      '#default_value' => $data['entity_type'] ?? '',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateTaskEditForm(array &$form, FormStateInterface $form_state) {
    $vtei = $form_state->getValue('vtei');

    if (empty($vtei['process_variable'])) {
      $form_state->setErrorByName('vtei][process_variable', $this->t('A process variable must be selected.'));
    }
    if (empty(trim($vtei['unique_id'] ?? ''))) {
      $form_state->setErrorByName('vtei][unique_id', $this->t('A unique ID must be provided.'));
    }
    if (empty($vtei['entity_type'])) {
      $form_state->setErrorByName('vtei][entity_type', $this->t('An entity type must be selected.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function prepareTaskForSave(array &$form, FormStateInterface $form_state, array &$task) {
    $vtei = $form_state->getValue('vtei');
    $task['data']['vtei'] = [
      'process_variable' => $vtei['process_variable'] ?? '',
      'unique_id' => trim($vtei['unique_id'] ?? ''),
      'entity_type' => $vtei['entity_type'] ?? '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function performValidityCheck(array &$validation_failure_tasks, array &$validation_information_tasks, array $task) {
    $data = $task['data']['vtei'] ?? [];

    if (empty($data['process_variable'])) {
      $validation_failure_tasks[] = [
        'taskID' => $task['id'],
        'taskLabel' => $task['label'],
        'reason' => $this->t('The Variable to Entity Identifier task is missing the process variable selection.'),
      ];
    }
    if (empty($data['unique_id'])) {
      $validation_failure_tasks[] = [
        'taskID' => $task['id'],
        'taskLabel' => $task['label'],
        'reason' => $this->t('The Variable to Entity Identifier task is missing the unique ID.'),
      ];
    }
    if (empty($data['entity_type'])) {
      $validation_failure_tasks[] = [
        'taskID' => $task['id'],
        'taskLabel' => $task['label'],
        'reason' => $this->t('The Variable to Entity Identifier task is missing the entity type selection.'),
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getTemplateBuilderCapabilities() {
    return ['edit', 'drawlineto', 'removelines', 'remove'];
  }

}
