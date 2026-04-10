<?php

namespace Drupal\maestro\Plugin\MaestroSetProcessVariablePlugins;

use Drupal\Core\Form\FormStateInterface;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\maestro\MaestroSetProcessVariablePluginInterface;
use Drupal\maestro\MaestroSetProcessVariablePluginBase;
use Drupal\node\Entity\NodeType;

/**
 * Provides a 'GetContentTypeFieldValue' Set Process Variable Plugin
 * This plugin returns the value of a content type's field.
 * 
 * @MaestroSetProcessVariablePlugin(
 *   id = "GetContentTypeFieldValue",
 *   short_description = @Translation("Get a content type field value using the entity identifier and field machine name"),
 *   description = @Translation("Get a content type field value using the entity identifier and field machine name"),
 * )
 */
class GetContentTypeFieldValue extends MaestroSetProcessVariablePluginBase implements MaestroSetProcessVariablePluginInterface {
  
  /**
   * {@inheritDoc}
   */
  public function getSPVTaskConfigFormElements() : array {
    /** @var FormStateInterface $form_state */
    $form = [];
    $form_state = $this->form_state;

    $form['get_content_field_data_info'] = [
      '#weight' => -5,
      '#markup' => 
        '<div class="messages">' .
        $this->t('Using the entity identifier and the config below, this plugin fetches the VALUE of a field.<br>') .
        $this->t('If you have special requirements for something other than the value (such as "target_id"), see the Maestro hooks.<br>') .
        '</div>',
    ];
    
    $entity_field_list = [];
    $entity_types = [];
    $content_types = NodeType::loadMultiple();
    $entityFieldManager = \Drupal::service('entity_field.manager');
    foreach ($content_types as $content_type) {
      $type_id = $content_type->id();
      $entity_types[$type_id] = $this->t('Node: ') . $content_type->label();
      $entity_field_list['node'][$type_id]['name'] = $content_type->label();
      $entity_field_list['node'][$type_id]['fields'] = [];
      $fields = $entityFieldManager->getFieldDefinitions('node', $type_id);
      foreach ($fields as $field_name => $field_definition) {
        /** @var \Drupal\Core\Field\BaseFieldDefinition $field_definition */
        if(strpos($field_name, 'field_') !== FALSE) {
          $entity_field_list['node'][$type_id]['fields'][$field_name] = $field_definition->getLabel();
        }
      }
    }

    // Let other modules supply their own types here
    \Drupal::moduleHandler()->alter('maestro_set_process_variable_entity_field_list', $entity_field_list);
    \Drupal::moduleHandler()->alter('maestro_set_process_variable_entity_types_list', $entity_types);

    $form_state_identifier = $form_state->getValue(['spv', 'plugin_wrapper', 'entity_identifier']);
    if($form_state_identifier && isset($form_state_identifier))  {
      $default_identifier = $form_state_identifier;
    }
    else {
      $default_identifier = $this->task['data']['spv']['entity_identifier'] ?? '';
    }
    $form['entity_identifier'] = [
      '#type' => 'textfield',
      '#title' => $this->t('What is the entity identifier?'),
      '#description' => $this->t('This plugin opens the node referenced by the identifier to dertermine the configured field\'s entry count.'),
      '#default_value' => $default_identifier,
    ];
    
    $default_entity_type = 'node';
    $form['entity_type'] = [
      '#type' => 'hidden',
      '#default_value' => 'node',
      '#value' => 'node',
    ];

    $triggering_element = $form_state->getTriggeringElement();
    if( isset($triggering_element) && 
        (
          $triggering_element['#name'] == 'spv[plugin_wrapper][entity_type_bundle]'
        )
    ) {
      // The entity type has changed.
      $form['entity_type_bundle'] = [
        '#markup' => $this->t('No fields on this entity type'),
      ];
      $entity_type = $form_state->getValue(['spv', 'plugin_wrapper', 'entity_type']);
      if($entity_type && array_key_exists($entity_type, $entity_field_list)) {
        // Get the fields for this entity type chosen
        $options = ['' => $this->t('Choose a Content Type')];
        $fields_array = array_combine(array_keys($entity_field_list[$entity_type]), array_keys($entity_field_list[$entity_type]));
        $options = array_merge($options, $fields_array);
        $form['entity_type_bundle'] = [
          '#type' => 'select',
          '#title' => $this->t('Choose the Content Type from the list'),
          '#options' => $options,
          '#ajax' => [
            'callback' => [$this, 'spvPluginBaseEntityCallback'],
            'event' => 'change',
            'wrapper' => 'spv-plugin-ajax-refresh-wrapper',
            'progress' => [
              'type' => 'throbber',
              'message' => NULL,
            ],
          ],
        ];
      }
      
      // Specifically going further, only if the bundle has changed.
      if($triggering_element['#name'] == 'spv[plugin_wrapper][entity_type_bundle]') {
        $entity_bundle = $triggering_element['#value'] ?? NULL;
        $entity_type = $form_state->getValue(['spv', 'plugin_wrapper', 'entity_type']);

        $options = ['' => $this->t('Choose the Content Type field')];
        $fields_array = array_combine(array_keys($entity_field_list[$entity_type][$entity_bundle]['fields']), array_keys($entity_field_list[$entity_type][$entity_bundle]['fields']));
        $options = array_merge($options, $fields_array);
        // Now get the fields
        $form['entity_type_field'] = [
          '#type' => 'select',
          '#title' => $this->t('Choose the Content Type\'s field from the list'),
          '#options' => $options,
        ];
      }
    }
    else { // Just loading
      $default_entity_bundle = $this->task['data']['spv']['entity_type_bundle'] ?? NULL;
      
      $options = ['' => $this->t('Choose the Content Type')];
      $fields_array = array_combine(array_keys($entity_field_list[$default_entity_type]), array_keys($entity_field_list[$default_entity_type]));
      $options = array_merge($options, $fields_array);
      $form['entity_type_bundle'] = [
        '#type' => 'select',
        '#title' => $this->t('Choose the Content Type from the list'),
        '#options' => $options,
        '#default_value' => $default_entity_bundle,
        '#ajax' => [
          'callback' => [$this, 'spvPluginBaseEntityCallback'],
          'event' => 'change',
          'wrapper' => 'spv-plugin-ajax-refresh-wrapper',
          'progress' => [
            'type' => 'throbber',
            'message' => NULL,
          ],
        ],
      ];

      if($default_entity_bundle) {
        $default_entity_field = $this->task['data']['spv']['entity_type_field'] ?? '';
        $options = ['' => $this->t('Choose the field')];
        $fields_array = array_combine(array_keys($entity_field_list[$default_entity_type][$default_entity_bundle]['fields']), array_keys($entity_field_list[$default_entity_type][$default_entity_bundle]['fields']));
        $options = array_merge($options, $fields_array);
        $form['entity_type_field'] = [
          '#type' => 'select',
          '#title' => $this->t('Choose the Content Type\'s field from the list'),
          '#options' => $options,
          '#default_value' => $default_entity_field,
        ];
      }
    }
    return $form;
  }

  public function spvPluginBaseEntityCallback(array &$form, FormStateInterface $form_state) {
    return $form['spv']['plugin_wrapper'];
  }

  /**
   * {@inheritDoc}
   */
  public function validateSPVTaskEditForm(array &$form, FormStateInterface $form_state) : void {
    $spv = $form_state->getValue(['spv', 'plugin_wrapper']);
    if($spv) {
      $entity_identifier = $spv['entity_identifier'] ?? NULL;
      $entity_type_bundle = $spv['entity_type_bundle'] ?? NULL;
      $entity_type_field = $spv['entity_type_field'] ?? NULL;
      
      if(!$entity_identifier || !$entity_type_bundle) {
        $form_state->setErrorByName('spv][plugin_wrapper', $this->t('You must configure all the fields of your selected Plugin before saving.'));
      }

      if(!$entity_type_field) {
        $form_state->setErrorByName('spv][plugin_wrapper][entity_type_field', $this->t('You must configure the field setting before saving.'));
      }
    }
    else {
      $form_state->setErrorByName('spv][plugin_wrapper', $this->t('You must configure your Plugin before saving.'));
    }
  }

  /**
   * {@inheritDoc}
   */
  public function execute() : ?string {
    // We'll load the entities the best we can.  Is there something special an entity does that isn't handled here?
    // If so, we have a hook that will let you retrieve the data.
    $returnValue = '';
    $spv = $this->task['data']['spv'];
    $uniqueIdentifier = $spv['entity_identifier'];
    $entity_identifier_data = MaestroEngine::getEntityIdentiferFieldsByUniqueID($this->processID, $uniqueIdentifier);
    $entity_type = $entity_identifier_data[$uniqueIdentifier]['entity_type'] ?? NULL;
    $entity_id = $entity_identifier_data[$uniqueIdentifier]['entity_id'] ?? NULL;
    $entity_bundle = $entity_identifier_data[$uniqueIdentifier]['bundle'] ?? NULL;
    // Load this entity
    $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id);
    if($entity) {
      // We have a match.  let's do our work now on this content type.
      $field = $spv['entity_type_field'];
      $field_ref = $entity->{$field};
      $returnValue = $field_ref->getValue();
      if (is_array($returnValue)) {
        $returnValue = current($returnValue)['value'];
      }
    }
    // Fire a hook to allow devs to manage the return value if for some reason there's an entity that doesn't
    // follow the EntityInterface or webform submission format.
    \Drupal::moduleHandler()->invokeAll('maestro_spv_get_content_type_field_value_plugin',
          [$this->queueID, $this->processID, $this->task, $this->templateMachineName, &$returnValue]);
    return $returnValue;
  }

  /**
   * {@inheritDoc}
   */
  public function prepareTaskForSave(array &$form, FormStateInterface $form_state, array &$task) : void {
    $spv = $form_state->getValue(['spv', 'plugin_wrapper']);
    $task['data']['spv']['entity_identifier'] = $spv['entity_identifier'];
    $task['data']['spv']['entity_type'] = $spv['entity_type'];
    $task['data']['spv']['entity_type_bundle'] = $spv['entity_type_bundle'];
    $task['data']['spv']['entity_type_field'] = $spv['entity_type_field'];
  }

  /**
   * {@inheritDoc}
   */
  public function performSPVTaskValidityCheck(array &$validation_failure_tasks, array &$validation_information_tasks, array $task) : void {
    // Detect if the settings are all filled in -- even though this should be the case through validation
    $spv = $task['data']['spv'];
    $entity_identifier = $spv['entity_identifier'] ?? NULL;
    $entity_type_bundle = $spv['entity_type_bundle'] ?? NULL;
    $entity_type_field = $spv['entity_type_field'] ?? NULL;
    
    if(!$entity_identifier || !$entity_type_bundle || !$entity_type_field) {
      $validation_failure_tasks[] = [
        'taskID' => $task['id'],
        'taskLabel' => $task['label'],
        'reason' => $this->t('There are missing settings for the Set Process Variable Plugin which will cause the task to hang.'),
      ];
    }
  }

}
