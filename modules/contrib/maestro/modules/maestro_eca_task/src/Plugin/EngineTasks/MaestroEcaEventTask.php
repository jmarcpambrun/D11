<?php

namespace Drupal\maestro_eca_task\Plugin\EngineTasks;

use Drupal\Core\Plugin\PluginBase;
use Drupal\maestro\MaestroEngineTaskInterface;
use Drupal\maestro\MaestroTaskTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca_base\BaseEvents;
use Drupal\eca_base\Event\CustomEvent;
use Drupal\eca_content\Event\ContentEntityCustomEvent;
use Drupal\eca_content\Event\ContentEntityEvents;
use Drupal\eca_content\Plugin\ECA\Event\ContentEntityEvent;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\maestro\Form\MaestroExecuteInteractive;
use Drupal\maestro_eca_task\Event\MaestroEcaEntityEvent;
use Entity;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Maestro ECA Event Task Task Plugin.
 *
 * The Maestro ECA Event Task.
 * This task allows you to configure the event you are firing and the associated entities and data
 * for the event
 *
 * @Plugin(
 *   id = "MaestroEcaEventTask",
 *   task_description = @Translation("ECA Event Task. Fire a Event for which ECA can manage."),
 * )
 */
class MaestroEcaEventTask extends PluginBase implements MaestroEngineTaskInterface {

  use MaestroTaskTrait;

  /**
   * {@inheritDoc}
   */
  public function __construct(array $configuration = NULL) {
    if (is_array($configuration)) {
      $this->processID = $configuration[0];
      $this->queueID = $configuration[1];
    }
  }

  /**
   * {@inheritDoc}
   */
  public function isInteractive() {
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function shortDescription() {
    return $this->t('ECA Event Task');
  }

  /**
   * {@inheritDoc}
   */
  public function description() {
    return $this->t('ECA Event Task. Fire a Event for which ECA can manage.');
  }

  /**
   * {@inheritDoc}
   *
   * @see \Drupal\Component\Plugin\PluginBase::getPluginId()
   */
  public function getPluginId() {
    return 'MaestroEcaEventTask';
  }

  /**
   * {@inheritDoc}
   */
  public function getTaskColours() {
    return '#606060';
  }

  /**
   * {@inheritdoc}.
   */
  public function execute() {
    $returnValue = FALSE;
    $templateMachineName = MaestroEngine::getTemplateIdFromProcessId($this->processID);
    $taskMachineName = MaestroEngine::getTaskIdFromQueueId($this->queueID);
    $task = MaestroEngine::getTemplateTaskByID($templateMachineName, $taskMachineName);

    $source = $task['data']['eca']['entity_identifier_source'];
    $identifier = $task['data']['eca']['entity_identifier_manual'] ?? NULL;
    if ($source == 'variable') {
      $task_pv = $task['data']['eca']['entity_identifier_variable'] ?? NULL;
      if($task_pv) {
        $identifier = MaestroEngine::getProcessVariable($task_pv, $this->processID);
      }
      else {
        \Drupal::logger('maestro_eca_task')->critical($this->t('Process Variable not defined for entity identifier. Please fix the task.'));
      }
    }

    if($identifier) {
      $entity_identifier_data = MaestroEngine::getEntityIdentiferFieldsByUniqueID($this->processID, $identifier);
      $entity_type = $entity_identifier_data[$identifier]['entity_type'] ?? NULL;
      $entity_id = $entity_identifier_data[$identifier]['entity_id'] ?? NULL;
      $event_name = $task['data']['eca']['event_name'] ?: 'maestro.eca_event';
      $entity = NULL;
      if($entity_type && $entity_id) {
        $storage = \Drupal::entityTypeManager()->getStorage($entity_type);
        $entity = $storage->load($entity_id);
      }
      if($entity) {
        $entity_types_service = \Drupal::service('eca.service.content_entity_types');
        $event = new ContentEntityCustomEvent($entity, $entity_types_service, $event_name, ['is_maestro' => 1, 'event_id' => $event_name, 'maestro_object' => $entity]);
        \Drupal::service('event_dispatcher')->dispatch($event, ContentEntityEvents::CUSTOM);
        $returnValue = TRUE; // This is the only path that completes the task.
      }
      else {
        \Drupal::logger('maestro_eca_task')->critical($this->t('The entity is unable to be loaded. QueueID: @queue_id', ['@queue_id' => $this->queueID]));
      }
      
    }
    else {
      \Drupal::logger('maestro_eca_task')->critical($this->t('The entity identifier is missing. Unable to continue. QueueID: @queue_id', ['@queue_id' => $this->queueID]));
    }
    return $returnValue;
  }

  /**
   * {@inheritDoc}
   */
  public function getExecutableForm($modal, MaestroExecuteInteractive $parent) {
    /*
     * This task has been set to be a non-interactive task, therefore we do not need to return a form
     */
  }

  /**
   * {@inheritDoc}
   */
  public function handleExecuteSubmit(array &$form, FormStateInterface $form_state) {
    /*
     * We don't need to do anything in this submit handler as we do not have any executable interface.
     */
  }

  /**
   * {@inheritDoc}
   */
  public function getTaskEditForm(array $task, $templateMachineName) {
    $form = [];
    
    $form['eca_info'] = [
      '#weight' => -5,
      '#markup' => 
        '<div class="messages">' .
        $this->t('This task will create an ECA custom event (entity-aware). Within ECA, you must insure that you are using actions which correspond to the entity you are creating the event for.<br>') . 
        $this->t('For example, if you have a Webform Submission and you\'ve created the ECA entity-aware event here, you can\'t use the ECA Publish Content Action on it.<br>') . 
        '</div>',
    ];

    $form['entity_identifier_source'] = [
      '#type' => 'radios',
      '#title' => $this->t('Entity Identifier Source'),
      '#options' => [
        'manual' => $this->t('Manual Entry'),
        'variable' => $this->t('Process Variable'),
      ],
      '#default_value' => $task['data']['eca']['entity_identifier_source'] ?? 'manual',
      '#required' => TRUE,
    ];
  
    $form['entity_identifier_manual'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Entity Identifier (Manual Entry)'),
      '#description' => $this->t('Enter the entity identifier manually (e.g., "submission").'),
      '#default_value' => $task['data']['eca']['entity_identifier_manual'] ?? '',
      '#required' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="entity_identifier_source"]' => ['value' => 'manual'],
        ],
      ],
    ];
  
    $options = ['' => $this->t('Choose the process variable')];
    $template_variables = MaestroEngine::getTemplateVariables($templateMachineName);
    foreach($template_variables as $variable_id => $variable_data) {
      $options[$variable_id] = $variable_id;
    }
    $form['entity_identifier_variable'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Process Variable'),
      '#description' => $this->t('Choose a process variable that contains the entity identifier.'),
      '#options' => $options,
      '#default_value' => $task['data']['eca']['entity_identifier_variable'] ?? '',
      '#required' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="entity_identifier_source"]' => ['value' => 'variable'],
        ],
      ],
    ];
  
    $form['event_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Event ID'),
      '#description' => $this->t('ECA custom event (entity-aware) event ID to dispatch for ECA. Defaults to "maestro.eca_event" if left blank. Use this ID in the Event ID ECA Configuration.'),
      '#default_value' => $task['data']['eca']['event_name'] ?? 'maestro.eca_event',
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function validateTaskEditForm(array &$form, FormStateInterface $form_state) {
    // Make sure we have an entity identifier validated here.
    $source = $form_state->getValue('entity_identifier_source');
  
    if ($source == 'manual') {
      $manual = $form_state->getValue('entity_identifier_manual');
      if(!$manual) {
        $form_state->setErrorByName('entity_identifier_manual', $this->t('You must enter a value for the entity identifier when using manual entry.'));
      }
    }
    else {
      $variable = $form_state->getValue('entity_identifier_variable');
      if(!$variable) {
        $form_state->setErrorByName('entity_identifier_variable', $this->t('You must select a process variable when using by-process-variable entry.'));
      }
    }
  }

  /**
   * {@inheritDoc}
   */
  public function prepareTaskForSave(array &$form, FormStateInterface $form_state, array &$task) {
    $task['data']['eca']['entity_identifier_source'] = $form_state->getValue('entity_identifier_source');
  
    if ($task['data']['eca']['entity_identifier_source'] == 'manual') {
      $task['data']['eca']['entity_identifier_manual'] = $form_state->getValue('entity_identifier_manual');
      $task['data']['eca']['entity_identifier_variable'] = NULL;  // Clear variable if manual is selected
    } 
    else {
      $task['data']['eca']['entity_identifier_variable'] = $form_state->getValue('entity_identifier_variable');
      $task['data']['eca']['entity_identifier_manual'] = NULL;  // Clear manual entry if variable is selected
    }
  
    $task['data']['eca']['event_name'] = $form_state->getValue('event_name');
  }

  /**
   * {@inheritDoc}
   */
  public function performValidityCheck(array &$validation_failure_tasks, array &$validation_information_tasks, array $task) {
    
  }

  /**
   * {@inheritDoc}
   */
  public function getTemplateBuilderCapabilities() {
    return ['edit', 'drawlineto', 'removelines', 'remove'];
  }

}
