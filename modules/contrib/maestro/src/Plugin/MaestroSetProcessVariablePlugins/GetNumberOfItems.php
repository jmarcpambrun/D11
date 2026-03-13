<?php

namespace Drupal\maestro\Plugin\MaestroSetProcessVariablePlugins;

use Drupal\Core\Form\FormStateInterface;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\maestro\MaestroSetProcessVariablePluginInterface;
use Drupal\maestro\MaestroSetProcessVariablePluginBase;
use Drupal\node\Entity\NodeType;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;

/**
 * Provides a 'GetNumberOfItems' Set Process Variable Plugin
 * This plugin returns the count of entries for a multi value field for the field specified.
 * This plugin does not perform calculations for multi value fields within multi value fields.
 * Only one level of field abstraction here.
 * 
 * @MaestroSetProcessVariablePlugin(
 *   id = "GetNumberOfItems",
 *   short_description = @Translation("Get the count of entries for a multi value field."),
 *   description = @Translation("Get the count of entries for a multi value field."),
 * )
 */
class GetNumberOfItems extends MaestroSetProcessVariablePluginBase implements MaestroSetProcessVariablePluginInterface {
  
  /**
   * {@inheritDoc}
   */
  public function getSPVTaskConfigFormElements() : array {
    /** @var FormStateInterface $form_state */
    $form = [];
    $form_state = $this->form_state;

    $form['set_number_of_items_info'] = [
      '#weight' => -5,
      '#markup' => 
        '<div class="messages">' .
        $this->t('If you have a multi-value field, you can retrieve the count of entries for that field.<br>') . 
        $this->t('Since you\'ve configured your workflow, it is likely that the entity identifier stores a linkage to a known entity type such as a node or webform submission.<br>') .
        $this->t('Using the entity identifier and the configured field options below, your process variable will be set ') . 
        $this->t('to the count of entries it has.<br>') .
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
        if(strpos($field_name, 'field_') !== FALSE || $field_name == 'body') {
          $entity_field_list['node'][$type_id]['fields'][$field_name] = $field_definition->getLabel();
        }
        
      }
    }

    // Get the webforms now.
    $webform_types = Webform::loadMultiple();
    foreach($webform_types as $webform_id => $webform) {
      /** @var \Drupal\webform\Entity\Webform $webform */
      $entity_field_list['webform_submission'][$webform_id]['name'] = $webform->label();
      $elements = $webform->getElementsDecodedAndFlattened();
      foreach($elements as $element_id => $element) {
        if($element['#type'] != 'webform_flexbox') {
          $entity_field_list['webform_submission'][$webform_id]['fields'][$element_id] = $element['#title'];
        }
      }
      $entity_types[$webform_id] = $this->t('Webform: ') . $webform->label();
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
      '#description' => $this->t('This plugin looks into the entity identified by the identifier to dertermine the field count.'),
      '#default_value' => $default_identifier,
    ];

    $form_state_entity_type = $form_state->getValue(['spv', 'plugin_wrapper', 'entity_type']);
    if($form_state_entity_type && isset($form_state_entity_type))  {
      $default_entity_type = $form_state_entity_type;
    }
    else {
      $default_entity_type = $this->task['data']['spv']['entity_type'] ?? '';
    }
    
    $form['entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Choose the base entity type'),
      '#options' => ['' => $this->t('Choose the base entity'), 'node' => 'Node', 'webform_submission' => 'Webform Submission'],
      '#default_value' => $default_entity_type,
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

    $triggering_element = $form_state->getTriggeringElement();
    if( isset($triggering_element) && 
        (
          $triggering_element['#name'] == 'spv[plugin_wrapper][entity_type]' ||
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
        $options = ['' => $this->t('Choose the entity bundle')];
        $fields_array = array_combine(array_keys($entity_field_list[$entity_type]), array_keys($entity_field_list[$entity_type]));
        $options = array_merge($options, $fields_array);
        $form['entity_type_bundle'] = [
          '#type' => 'select',
          '#title' => $this->t('Choose the entity bundle from the list'),
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

        $options = ['' => $this->t('Choose the field')];
        $fields_array = array_combine(array_keys($entity_field_list[$entity_type][$entity_bundle]['fields']), array_keys($entity_field_list[$entity_type][$entity_bundle]['fields']));
        $options = array_merge($options, $fields_array);
        // Now get the fields
        $form['entity_type_field'] = [
          '#type' => 'select',
          '#title' => $this->t('Choose the entity\'s field from the list'),
          '#options' => $options,
        ];
      }
    }
    else { // Just loading
      $default_entity_bundle = $this->task['data']['spv']['entity_type_bundle'] ?? NULL;
      if($default_entity_bundle) {
        $options = ['' => $this->t('Choose the entity bundle')];
        $fields_array = array_combine(array_keys($entity_field_list[$default_entity_type]), array_keys($entity_field_list[$default_entity_type]));
        $options = array_merge($options, $fields_array);
        $form['entity_type_bundle'] = [
          '#type' => 'select',
          '#title' => $this->t('Choose the entity bundle from the list'),
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

        $default_entity_field = $this->task['data']['spv']['entity_type_field'] ?? '';
        $options = ['' => $this->t('Choose the field')];
        $fields_array = array_combine(array_keys($entity_field_list[$default_entity_type][$default_entity_bundle]['fields']), array_keys($entity_field_list[$default_entity_type][$default_entity_bundle]['fields']));
        $options = array_merge($options, $fields_array);
        $form['entity_type_field'] = [
          '#type' => 'select',
          '#title' => $this->t('Choose the entity\'s field from the list'),
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
    // When you configure the Get Number of Items plugin, there needs to be some defaults set.
    // Such as, well, all of the fields!
    $spv = $form_state->getValue(['spv', 'plugin_wrapper']);
    if($spv) {
      $entity_type = $spv['entity_type'] ?? NULL;
      $entity_identifier = $spv['entity_identifier'] ?? NULL;
      $entity_type_bundle = $spv['entity_type_bundle'] ?? NULL;
      $entity_type_field = $spv['entity_type_field'] ?? NULL;
      
      if(!$entity_type || !$entity_identifier || !$entity_type_bundle) {
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
      $retrieved_entity_type = $entity->getEntityTypeId();
      $field = $spv['entity_type_field'];
      // Is this a webform submission?  The data is stored differently
      if($entity_type == 'webform_submission' && $retrieved_entity_type == $entity_type) {
        /** @var WebformSubmission $entity */
        $submission_data = $entity->getData();
        if(array_key_exists($field, $submission_data)) {
          $returnValue = count($submission_data[$field]);
        }
      }
      elseif($retrieved_entity_type == $entity_type) {
        /** @var \Drupal\Core\Entity\EntityInterface $entity */
        $returnValue = $entity->get($field)->count();
      }
    }
    // Fire a hook to allow devs to manage the return value if for some reason there's an entity that doesn't
    // follow the EntityInterface or webform submission format.
    \Drupal::moduleHandler()->invokeAll('maestro_spv_set_number_of_items_plugin',
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
    $entity_type = $spv['entity_type'] ?? NULL;
    $entity_identifier = $spv['entity_identifier'] ?? NULL;
    $entity_type_bundle = $spv['entity_type_bundle'] ?? NULL;
    $entity_type_field = $spv['entity_type_field'] ?? NULL;
    
    if(!$entity_type || !$entity_identifier || !$entity_type_bundle || !$entity_type_field) {
      $validation_failure_tasks[] = [
        'taskID' => $task['id'],
        'taskLabel' => $task['label'],
        'reason' => $this->t('There are missing settings for the Set Process Variable Plugin which will cause the task to hang.'),
      ];
    }
  }

}
