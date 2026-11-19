<?php

namespace Drupal\maestro\Plugin\MaestroSetProcessVariablePlugins;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\maestro\MaestroSetProcessVariablePluginBase;
use Drupal\maestro\MaestroSetProcessVariablePluginInterface;

/**
 * Provides a 'GetEntityFieldValue' Set Process Variable Plugin.
 *
 * Works with any content entity type in the system (node, user, taxonomy
 * term, webform submission, media, ...).  The designer chooses the entity
 * type, then any base property or configurable field on that entity type,
 * and optionally which column of the field (value, target_id, format...).
 *
 * The entity is located either through a Maestro entity identifier or
 * through a process variable that holds the entity's ID (for example, the
 * "initiator" process variable holds a user ID).
 *
 * @MaestroSetProcessVariablePlugin(
 *   id = "GetEntityFieldValue",
 *   short_description = @Translation("Get any entity type's property or field value"),
 *   description = @Translation("Choose any entity type, then one of its properties or fields, and store that value in the process variable."),
 * )
 */
class GetEntityFieldValue extends MaestroSetProcessVariablePluginBase implements MaestroSetProcessVariablePluginInterface {

  /**
   * Field types that are never offered, as their values must not leak into
   * process variables.
   */
  const EXCLUDED_FIELD_TYPES = ['password'];

  /**
   * {@inheritDoc}
   */
  public function getSPVTaskConfigFormElements() : array {
    $form = [];

    $form['get_entity_field_value_info'] = [
      '#weight' => -5,
      '#markup' =>
        '<div class="messages">' .
        $this->t('Choose any entity type, then one of its properties or fields. The value is stored in the process variable.<br>') .
        $this->t('The entity is loaded either from an entity identifier or from a process variable that holds the entity ID (e.g. the "initiator" variable holds a user name, not ID).<br>') .
        $this->t('<em><strong>You must know what is stored in a process variable before trying to set values! Process variables can store up to 10,000 characters.</strong></em>') .
        '</div>',
    ];

    // Where does the entity ID come from?
    $entity_source = $this->getCurrentValue('entity_source') ?: 'identifier';
    $form['entity_source'] = [
      '#type' => 'radios',
      '#title' => $this->t('How is the entity located?'),
      '#options' => [
        'identifier' => $this->t('By entity identifier'),
        'variable' => $this->t('By a process variable holding the entity ID'),
      ],
      '#default_value' => $entity_source,
    ];

    $form['entity_identifier'] = [
      '#type' => 'textfield',
      '#title' => $this->t('What is the entity identifier?'),
      '#description' => $this->t('The unique identifier of the entity as attached to this process.'),
      '#default_value' => $this->getCurrentValue('entity_identifier') ?? '',
      '#states' => [
        'visible' => [
          ':input[name="spv[plugin_wrapper][entity_source]"]' => ['value' => 'identifier'],
        ],
      ],
    ];

    $variable_options = ['' => $this->t('Choose the process variable')];
    foreach (array_keys(MaestroEngine::getTemplateVariables($this->templateMachineName) ?? []) as $variable_name) {
      $variable_options[$variable_name] = $variable_name;
    }
    $form['entity_id_variable'] = [
      '#type' => 'select',
      '#title' => $this->t('Process variable holding the entity ID'),
      '#options' => $variable_options,
      '#default_value' => $this->getCurrentValue('entity_id_variable') ?? '',
      '#states' => [
        'visible' => [
          ':input[name="spv[plugin_wrapper][entity_source]"]' => ['value' => 'variable'],
        ],
      ],
    ];

    // Entity type.
    $entity_type_options = $this->getEntityTypeOptions();
    $entity_type = $this->getValidCurrentValue('entity_type', $entity_type_options);
    $form['entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Choose the entity type'),
      '#options' => ['' => $this->t('Choose the entity type')] + $entity_type_options,
      '#default_value' => $entity_type,
      '#ajax' => $this->ajaxSettings(),
    ];

    if (!$entity_type) {
      return $form;
    }

    // Property or field on the entity type.
    $field_options = $this->getFieldOptions($entity_type);
    $flat_field_options = array_merge([], ...array_values($field_options));
    $entity_field = $this->getValidCurrentValue('entity_field', $flat_field_options);
    $form['entity_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Choose the property or field'),
      '#options' => ['' => $this->t('Choose the property or field')] + $field_options,
      '#default_value' => $entity_field,
      '#ajax' => $this->ajaxSettings(),
    ];

    if (!$entity_field) {
      return $form;
    }

    // Which column of the field.
    $property_options = $this->getPropertyOptions($entity_type, $entity_field);
    $form['field_property'] = [
      '#type' => 'select',
      '#title' => $this->t('Which value of the field?'),
      '#description' => $this->t('Most fields store their data in "value". Entity references (such as uid) store the referenced ID in "target_id".'),
      '#options' => $property_options,
      '#default_value' => $this->getValidCurrentValue('field_property', $property_options) ?? array_key_first($property_options),
    ];

    $form['multiple_values'] = [
      '#type' => 'radios',
      '#title' => $this->t('If the field has more than one value'),
      '#options' => [
        'first' => $this->t('Use the first value'),
        'all' => $this->t('Use all values, comma separated'),
      ],
      '#default_value' => $this->getCurrentValue('multiple_values') ?: 'first',
    ];

    return $form;
  }

  /**
   * AJAX callback: rebuild the plugin's wrapper.
   */
  public function spvPluginBaseEntityCallback(array &$form, FormStateInterface $form_state) {
    return $form['spv']['plugin_wrapper'];
  }

  /**
   * {@inheritDoc}
   */
  public function validateSPVTaskEditForm(array &$form, FormStateInterface $form_state) : void {
    $spv = $form_state->getValue(['spv', 'plugin_wrapper']);
    if (!$spv) {
      $form_state->setErrorByName('spv][plugin_wrapper', $this->t('You must configure your Plugin before saving.'));
      return;
    }

    $source = $spv['entity_source'] ?? 'identifier';
    if ($source == 'identifier' && empty($spv['entity_identifier'])) {
      $form_state->setErrorByName('spv][plugin_wrapper][entity_identifier', $this->t('You must provide the entity identifier.'));
    }
    if ($source == 'variable' && empty($spv['entity_id_variable'])) {
      $form_state->setErrorByName('spv][plugin_wrapper][entity_id_variable', $this->t('You must choose the process variable holding the entity ID.'));
    }
    if (empty($spv['entity_type'])) {
      $form_state->setErrorByName('spv][plugin_wrapper][entity_type', $this->t('You must choose the entity type.'));
    }
    if (empty($spv['entity_field'])) {
      $form_state->setErrorByName('spv][plugin_wrapper][entity_field', $this->t('You must choose the property or field.'));
    }
  }

  /**
   * {@inheritDoc}
   */
  public function execute() : ?string {
    $returnValue = '';
    $spv = $this->task['data']['spv'] ?? [];
    $field = $spv['entity_field'] ?? '';

    $entity = $this->loadConfiguredEntity($spv);
    if ($entity instanceof FieldableEntityInterface && $field && $entity->hasField($field)) {
      $items = $entity->get($field);
      $property = $spv['field_property'] ?? '';
      if (!$property) {
        $property = $items->getFieldDefinition()->getFieldStorageDefinition()->getMainPropertyName() ?? 'value';
      }

      $values = [];
      foreach ($items as $item) {
        try {
          $value = $item->get($property)->getValue();
        }
        catch (\InvalidArgumentException $e) {
          continue;
        }
        if (is_scalar($value)) {
          $values[] = (string) $value;
        }
      }

      if (($spv['multiple_values'] ?? 'first') == 'all') {
        $returnValue = implode(', ', $values);
      }
      else {
        $returnValue = $values[0] ?? '';
      }
    }

    // Allow developers to alter or supply the value for special cases.
    \Drupal::moduleHandler()->invokeAll('maestro_spv_get_entity_field_value_plugin',
      [$this->queueID, $this->processID, $this->task, $this->templateMachineName, &$returnValue]);

    return $returnValue;
  }

  /**
   * {@inheritDoc}
   */
  public function prepareTaskForSave(array &$form, FormStateInterface $form_state, array &$task) : void {
    $spv = $form_state->getValue(['spv', 'plugin_wrapper']);
    foreach (['entity_source', 'entity_identifier', 'entity_id_variable', 'entity_type', 'entity_field', 'field_property', 'multiple_values'] as $key) {
      $task['data']['spv'][$key] = $spv[$key] ?? '';
    }
  }

  /**
   * {@inheritDoc}
   */
  public function performSPVTaskValidityCheck(array &$validation_failure_tasks, array &$validation_information_tasks, array $task) : void {
    $spv = $task['data']['spv'] ?? [];
    $source = $spv['entity_source'] ?? 'identifier';
    $locator = $source == 'variable' ? ($spv['entity_id_variable'] ?? NULL) : ($spv['entity_identifier'] ?? NULL);

    if (!$locator || empty($spv['entity_type']) || empty($spv['entity_field'])) {
      $validation_failure_tasks[] = [
        'taskID' => $task['id'],
        'taskLabel' => $task['label'],
        'reason' => $this->t('There are missing settings for the Set Process Variable Plugin which will cause the task to hang.'),
      ];
    }
  }

  /**
   * Loads the entity described by the saved configuration.
   *
   * @param array $spv
   *   The saved SPV plugin configuration.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity, or NULL when it can't be found.
   */
  protected function loadConfiguredEntity(array $spv) {
    $entity_type = $spv['entity_type'] ?? '';
    if (!$entity_type || !\Drupal::entityTypeManager()->hasDefinition($entity_type)) {
      return NULL;
    }

    $entity_id = NULL;
    if (($spv['entity_source'] ?? 'identifier') == 'variable') {
      $variable_value = MaestroEngine::getProcessVariable($spv['entity_id_variable'] ?? '', $this->processID);
      if ($variable_value !== FALSE && $variable_value !== NULL) {
        $entity_id = trim((string) $variable_value);
      }
    }
    else {
      $unique_id = $spv['entity_identifier'] ?? '';
      $identifier = MaestroEngine::getEntityIdentiferFieldsByUniqueID($this->processID, $unique_id);
      // The identifier must point at the entity type chosen in the config.
      if (($identifier[$unique_id]['entity_type'] ?? NULL) == $entity_type) {
        $entity_id = $identifier[$unique_id]['entity_id'] ?? NULL;
      }
    }

    if ($entity_id === NULL || $entity_id === '') {
      return NULL;
    }
    return \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id);
  }

  /**
   * All fieldable content entity types, keyed by machine name.
   */
  protected function getEntityTypeOptions() : array {
    $options = [];
    foreach (\Drupal::entityTypeManager()->getDefinitions() as $id => $definition) {
      if ($definition instanceof ContentEntityTypeInterface && $definition->entityClassImplements(FieldableEntityInterface::class)) {
        $options[$id] = $definition->getLabel() . ' (' . $id . ')';
      }
    }
    asort($options, SORT_NATURAL | SORT_FLAG_CASE);
    return $options;
  }

  /**
   * Base properties and bundle fields of an entity type, grouped for a select.
   */
  protected function getFieldOptions(string $entity_type) : array {
    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager */
    $field_manager = \Drupal::service('entity_field.manager');
    $properties = [];
    $fields = [];

    $base_fields = $field_manager->getBaseFieldDefinitions($entity_type);
    foreach ($base_fields as $name => $definition) {
      if (!in_array($definition->getType(), self::EXCLUDED_FIELD_TYPES)) {
        $properties[$name] = $definition->getLabel() . ' (' . $name . ')';
      }
    }

    // Configurable/bundle fields, noting which bundles each one is on.
    $bundle_fields = [];
    $bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo($entity_type);
    foreach ($bundles as $bundle => $bundle_info) {
      foreach ($field_manager->getFieldDefinitions($entity_type, $bundle) as $name => $definition) {
        if (isset($base_fields[$name]) || in_array($definition->getType(), self::EXCLUDED_FIELD_TYPES)) {
          continue;
        }
        $bundle_fields[$name]['label'] = $definition->getLabel();
        $bundle_fields[$name]['bundles'][] = $bundle_info['label'] ?? $bundle;
      }
    }
    foreach ($bundle_fields as $name => $info) {
      $label = $info['label'] . ' (' . $name . ')';
      // Only worth listing the bundles when the entity type has several.
      if (count($bundles) > 1) {
        $label .= ' - ' . implode(', ', $info['bundles']);
      }
      $fields[$name] = $label;
    }

    asort($properties, SORT_NATURAL | SORT_FLAG_CASE);
    asort($fields, SORT_NATURAL | SORT_FLAG_CASE);

    $options = [];
    if ($properties) {
      $options[(string) $this->t('Properties')] = $properties;
    }
    if ($fields) {
      $options[(string) $this->t('Fields')] = $fields;
    }
    return $options;
  }

  /**
   * The stored (non-computed) columns of a field, main property first.
   */
  protected function getPropertyOptions(string $entity_type, string $field_name) : array {
    $storage_definitions = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions($entity_type);
    $storage = $storage_definitions[$field_name] ?? NULL;
    if (!$storage) {
      return ['' => $this->t('Default')];
    }

    $main = $storage->getMainPropertyName();
    $options = [];
    if ($main) {
      $options[$main] = $this->t('@property (default)', ['@property' => $main]);
    }
    foreach ($storage->getPropertyDefinitions() as $name => $definition) {
      if ($name != $main && !$definition->isComputed()) {
        $options[$name] = $name;
      }
    }
    return $options ?: ['' => $this->t('Default')];
  }

  /**
   * AJAX settings shared by the selects that rebuild the wrapper.
   */
  protected function ajaxSettings() : array {
    return [
      'callback' => [$this, 'spvPluginBaseEntityCallback'],
      'event' => 'change',
      'wrapper' => 'spv-plugin-ajax-refresh-wrapper',
      'progress' => [
        'type' => 'throbber',
        'message' => NULL,
      ],
    ];
  }

  /**
   * Submitted value during an AJAX rebuild, otherwise the saved value.
   */
  protected function getCurrentValue(string $key) {
    $form_state = $this->form_state;
    if ($form_state && $form_state->hasValue(['spv', 'plugin_wrapper', $key])) {
      return $form_state->getValue(['spv', 'plugin_wrapper', $key]);
    }
    return $this->task['data']['spv'][$key] ?? NULL;
  }

  /**
   * Like getCurrentValue(), but NULL when the value isn't one of the options.
   *
   * When the entity type changes, the previously chosen field may not exist
   * on the new type, so it is dropped (and cleared from user input so the
   * rebuilt select doesn't try to keep it).
   */
  protected function getValidCurrentValue(string $key, array $options) {
    $value = $this->getCurrentValue($key);
    if ($value !== NULL && $value !== '' && array_key_exists($value, $options)) {
      return $value;
    }
    if ($this->form_state) {
      $input = $this->form_state->getUserInput();
      if (isset($input['spv']['plugin_wrapper'][$key])) {
        unset($input['spv']['plugin_wrapper'][$key]);
        $this->form_state->setUserInput($input);
      }
    }
    return NULL;
  }

}
