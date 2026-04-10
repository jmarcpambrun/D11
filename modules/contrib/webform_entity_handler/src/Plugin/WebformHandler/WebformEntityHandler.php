<?php

namespace Drupal\webform_entity_handler\Plugin\WebformHandler;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\Utility\Error;
use Drupal\webform\Element\WebformSelectOther;
use Drupal\webform\Plugin\WebformElement\WebformCompositeBase;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Create or update an entity with a webform submission values.
 *
 * @WebformHandler(
 *   id = "webform_entity_handler",
 *   label = @Translation("Entity"),
 *   category = @Translation("External"),
 *   description = @Translation("Create or update an entity with the webform submission values."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_UNLIMITED,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 *   tokens = TRUE
 * )
 */
class WebformEntityHandler extends WebformHandlerBase {

  /**
   * Default value. (This is used by the handler's settings.)
   */
  const DEFAULT_VALUE = '_default';

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The webform element manager.
   *
   * @var \Drupal\webform\Plugin\WebformElementManagerInterface
   */
  protected $webformElementManager;


  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->entityTypeBundleInfo = $container->get('entity_type.bundle.info');
    $instance->webformElementManager = $container->get('plugin.manager.webform.element');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'operation' => self::DEFAULT_VALUE,
      'skip_if_exists' => NULL,
      'entity_properties' => '',
      'entity_type_id' => NULL,
      'entity_values' => [],
      'entity_revision' => FALSE,
      'states' => [WebformSubmissionInterface::STATE_COMPLETED],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $configuration = $this->getConfiguration();
    $settings = $configuration['settings'];

    $states = [
      WebformSubmissionInterface::STATE_DRAFT_CREATED => $this->t('Draft created'),
      WebformSubmissionInterface::STATE_DRAFT_UPDATED => $this->t('Draft updated'),
      WebformSubmissionInterface::STATE_CONVERTED => $this->t('Converted'),
      WebformSubmissionInterface::STATE_COMPLETED => $this->t('Completed'),
      WebformSubmissionInterface::STATE_UPDATED => $this->t('Updated'),
      WebformSubmissionInterface::STATE_LOCKED => $this->t('Locked'),
    ];
    $settings['states'] = array_intersect_key($states, array_combine($settings['states'], $settings['states']));

    return [
      '#settings' => $settings,
    ] + parent::getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $this->applyFormStateToConfiguration($form_state);

    // Get the webform elements options array.
    $webform_elements = $this->getElements();

    // Entity settings.
    $form['entity_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Entity settings'),
      '#collapsible' => FALSE,
    ];
    $form['entity_settings']['operation'] = [
      '#type' => 'webform_select_other',
      '#title' => $this->t('Entity operation'),
      '#description' => $this->t('If the entity ID is empty a new entity will be created and then updated with the new entity ID.'),
      '#options' => [
        self::DEFAULT_VALUE => $this->t('Create a new entity'),
        $this->t('or update entity ID stored in the following submission element:')->__toString() => $webform_elements,
        WebformSelectOther::OTHER_OPTION => $this->t('Update custom entity ID…'),
      ],
      '#empty_option' => $this->t('- Select -'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['operation'],
      '#parents' => [
        'settings',
        'operation',
      ],
    ];
    $form['entity_settings']['skip_if_exists'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Skip the execution of this handler if the entity already exists'),
      '#default_value' => $this->configuration['skip_if_exists'],
      '#parents' => [
        'settings',
        'skip_if_exists',
      ],
    ];
    $form['entity_settings']['entity_properties'] = [
      '#type' => 'webform_codemirror',
      '#mode' => 'yaml',
      '#title' => $this->t('Load by properties'),
      '#description' => $this->t('If you do not know the entity ID, but would like to update it if it exists, you could try to load it by properties. Add here the properties with the values you want to use to load the entity, tokens are supported.'),
      '#default_value' => $this->configuration['entity_properties'],
      '#attributes' => ['style' => 'min-height: 150px'],
      '#parents' => [
        'settings',
        'entity_properties',
      ],
    ];
    $form['entity_settings']['entity_type_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity type'),
      '#options' => $this->getEntityTypes(),
      '#empty_option' => $this->t('- Select -'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['entity_type_id'],
      '#ajax' => [
        'callback' => [get_called_class(), 'updateEntityFields'],
        'wrapper' => 'webform-entity-handler--entity-values',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Loading fields...'),
        ],
      ],
      '#parents' => [
        'settings',
        'entity_type_id',
      ],
    ];
    $form['entity_settings']['entity_values'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Entity values'),
      '#attributes' => [
        'id' => 'webform-entity-handler--entity-values',
      ],
      '#collapsible' => FALSE,
    ];
    if (!empty($this->configuration['entity_type_id'])) {
      $form['entity_settings']['entity_values'] += $this->getEntityFieldsForm($this->configuration['entity_type_id']);
    }
    $form['entity_settings']['entity_revision'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Create new revision'),
      '#default_value' => $this->configuration['entity_revision'],
      '#access' => FALSE,
      '#parents' => [
        'settings',
        'entity_revision',
      ],
    ];
    if (!empty($this->configuration['entity_type_id'])) {
      [$type] = explode(':', $this->configuration['entity_type_id']);

      $form['entity_settings']['entity_revision']['#access'] = $this->entityTypeManager->getDefinition($type)->isRevisionable();
    }

    $form['token_tree_link'] = $this->tokenManager->buildTreeLink();

    // Additional.
    $results_disabled = $this->getWebform()->getSetting('results_disabled');
    $form['additional'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Additional settings'),
    ];
    // Settings: States.
    $form['additional']['states'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Execute'),
      '#options' => [
        WebformSubmissionInterface::STATE_DRAFT_CREATED => $this->t('…when <b>draft</b> is created.'),
        WebformSubmissionInterface::STATE_DRAFT_UPDATED => $this->t('…when <b>draft</b> is updated.'),
        WebformSubmissionInterface::STATE_CONVERTED => $this->t('…when anonymous submission is <b>converted</b> to authenticated.'),
        WebformSubmissionInterface::STATE_COMPLETED => $this->t('…when submission is <b>completed</b>.'),
        WebformSubmissionInterface::STATE_UPDATED => $this->t('…when submission is <b>updated</b>.'),
        WebformSubmissionInterface::STATE_DELETED => $this->t('…when submission is <b>deleted</b>.'),
      ],
      '#parents' => [
        'settings',
        'states',
      ],
      '#access' => $results_disabled ? FALSE : TRUE,
      '#default_value' => $results_disabled ? [WebformSubmissionInterface::STATE_COMPLETED] : $this->configuration['states'],
    ];

    if (method_exists($this, 'elementTokenValidate')) {
      $this->elementTokenValidate($form);
    }

    return $this->setSettingsParents($form);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->hasAnyErrors()) {
      return;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->applyFormStateToConfiguration($form_state);

    // Cleanup states.
    $this->configuration['states'] = array_values(array_filter($this->configuration['states']));

    // Cleanup entity values.
    $this->configuration['entity_values'] = array_map('array_filter', $this->configuration['entity_values']);
    $this->configuration['entity_values'] = array_filter($this->configuration['entity_values']);
  }

  /**
   * Ajax callback for the "Entity values" options form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response.
   */
  public static function updateEntityFields(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();

    $array_parents = array_slice($triggering_element['#array_parents'], 0, -1);
    $element = NestedArray::getValue($form, $array_parents);

    $response = new AjaxResponse();
    $response->addCommand(
      new ReplaceCommand(
        '#webform-entity-handler--entity-values',
        $element['entity_values']
      )
    );

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    $state = $webform_submission->getWebform()->getSetting('results_disabled') ? WebformSubmissionInterface::STATE_COMPLETED : $webform_submission->getState();
    if (in_array($state, $this->configuration['states'])) {
      // Get the handler configuration and replace the values of the mapped
      // elements.
      $data = $this->configuration['entity_values'];
      $submission_data = $webform_submission->getData();
      array_walk_recursive($data, function (&$value) use ($webform_submission, $submission_data) {
        if (strpos($value, 'input:') !== FALSE) {
          [, $element_key] = explode(':', $value);
          $element_key = explode('|', $element_key);
          $value = NestedArray::getValue($submission_data, $element_key);
        }
        elseif ($value === '_null_') {
          $value = NULL;
        }

        $value = $this->tokenManager->replace($value, $webform_submission, [], ['clear' => TRUE]);
      });

      [$type, $bundle] = explode(':', $this->configuration['entity_type_id']);

      // Add the bundle value if the entity has one.
      if ($this->entityTypeManager->getDefinition($type)->hasKey('bundle')) {
        $data[$this->entityTypeManager->getDefinition($type)->getKey('bundle')] = $bundle;
      }
      try {
        $entity_id = FALSE;

        if ($this->configuration['operation'] != self::DEFAULT_VALUE) {
          if (strpos($this->configuration['operation'], 'input:') !== FALSE) {
            [, $element_key] = explode(':', $this->configuration['operation']);
            $element_key = explode('|', $element_key);
            $entity_id = NestedArray::getValue($submission_data, $element_key);
          }
          else {
            $entity_id = $this->configuration['operation'];
          }

          $entity_id = $this->tokenManager->replace($entity_id, $webform_submission);
        }

        if (!empty($entity_id) || !empty($this->configuration['entity_properties'])) {
          // Load the entity, first try with the entity ID.
          if (!empty($entity_id)) {
            $entity = $this->entityTypeManager->getStorage($type)->load($entity_id);
          }
          if (empty($entity)) {
            // If the entity ID is empty or the entity does not exist, try to
            // load it by properties.
            $entity_properties = $this->tokenManager->replace($this->configuration['entity_properties'], $webform_submission);
            $entity_properties = Yaml::decode($entity_properties);
            $entity = $this->entityTypeManager->getStorage($type)->loadByProperties($entity_properties);
            $entity = reset($entity);
          }

          // Update the values.
          if (!empty($entity)) {
            if (!empty($this->configuration['skip_if_exists'])) {
              return;
            }

            // If the new values change the bundle we need to remove it first.
            if ($this->entityTypeManager->getDefinition($type)->hasKey('bundle') && $bundle != $entity->bundle()) {
              /** @var \Drupal\Core\Entity\EntityInterface $previous_entity */
              $previous_entity = clone $entity;
              $entity->delete();
              unset($entity);

              // If the previous entity has the field of the current one,
              // it has value, and in the submission there is no value,
              // we recover it.
              foreach (array_keys($data) as $field_name) {
                if (empty($data[$field_name]) && $previous_entity->hasField($field_name) && !$previous_entity->get($field_name)->isEmpty()) {
                  $data[$field_name] = $previous_entity->get($field_name)->getValue();
                }
              }

              // Ensure the entity preserve its ID.
              $data['id'] = $previous_entity->id();
              $data['uuid'] = $previous_entity->uuid();
            }
            // Otherwise just update the values.
            else {
              foreach ($data as $field => $value) {
                $append = !empty($value['webform_entity_handler_append']);
                if (isset($value['webform_entity_handler_append'])) {
                  unset($value['webform_entity_handler_append']);
                }

                if ($append && !$entity->get($field)->isEmpty()) {
                  $entity->get($field)->appendItem($value);
                }
                else {
                  $entity->set($field, $value);
                }
              }
            }
          }
        }

        if (empty($entity)) {
          // Create the entity with the values.
          $entity = $this->entityTypeManager->getStorage($type)->create($data);
        }

        if ($this->entityTypeManager->getDefinition($type)->isRevisionable()) {
          $entity->setNewRevision($this->configuration['entity_revision']);
        }

        if ($entity->save() == SAVED_NEW) {
          $message = '@type %title has been created.';
        }
        else {
          $message = '@type %title has been updated.';
        }

        $context = [
          '@type' => $entity->getEntityType()->getLabel(),
          '%title' => $entity->label(),
        ];
        if ($entity->hasLinkTemplate('canonical')) {
          $context += [
            'link' => $entity->toLink($this->t('View'))->toString(),
          ];
        }

        if ($webform_submission->getWebform()->hasSubmissionLog()) {
          // Log detailed message to the 'webform_submission' log.
          $context += [
            'link' => ($webform_submission->id()) ? $webform_submission->toLink($this->t('View'))->toString() : NULL,
            'webform_submission' => $webform_submission,
            'handler_id' => $this->getHandlerId(),
            'operation' => 'sent email',
          ];
          $this->getLogger('webform_submission')->notice($message, $context);
        }
        else {
          // Log general message to the 'webform_entity_handler' log.
          $context += [
            'link' => $this->getWebform()->toLink($this->t('Edit'), 'handlers')->toString(),
          ];
          $this->getLogger('webform_entity_handler')->notice($message, $context);
        }

        // Update the entity ID.
        if ($entity &&
          $this->configuration['operation'] != self::DEFAULT_VALUE &&
          strpos($this->configuration['operation'], 'input:') !== FALSE) {

          [, $element_key] = explode(':', $this->configuration['operation']);
          $element_key = explode('|', $element_key);
          $webform_submission->setElementData(end($element_key), $entity->id());
          $webform_submission->resave();
        }
      }
      catch (\Exception $exception) {
        if (method_exists(Error::class, 'logException')) {
          Error::logException($this->getLogger('webform_entity_handler'), $exception);
        }
        else {
          \Drupal\Component\Utility\DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '10.1.0', fn() => \Drupal\Core\Utility\Error::logException(\Drupal::logger('webform_entity_handler'), $exception), fn() => watchdog_exception('webform_entity_handler', $exception));
        }
        $this->messenger()->addError($this->t('There was a problem processing your request. Please, try again.'));
      }
    }
  }

  /**
   * Prepare #options array of webform elements.
   *
   * @return array
   *   Prepared array of webform elements.
   */
  protected function getElements() {
    $elements_options = &drupal_static(__FUNCTION__);

    if (is_null($elements_options)) {
      $elements_options = [];
      foreach ($this->getWebform()->getElementsInitializedAndFlattened() as $element) {
        try {
          $element_plugin = $this->webformElementManager->getElementInstance($element);
          if (!$element_plugin instanceof WebformCompositeBase) {
            $t_args = [
              '@title' => $element['#title'],
              '@type' => $element_plugin->getPluginLabel(),
            ];
            $elements_options['input:' . $element['#webform_key']] = $this->t('@title [@type]', $t_args);
          }
          else {
            $element_group = $element_plugin->getElementSelectorOptions($element);
            foreach ($element_group as $group_key => $group) {
              foreach ($group as $sub_element_key => $sub_element) {
                if (preg_match('/^:input\[name=\"(.*?)\"]$/', $sub_element_key, $match) == TRUE) {
                  $sub_element_key = array_map(
                    function ($item) {
                      return rtrim($item, ']');
                    },
                    explode('[', $match[1])
                  );

                  // Manged file add a non-existent first key.
                  if ($element['#webform_composite_elements'][end($sub_element_key)]['#type'] == 'managed_file') {
                    array_shift($sub_element_key);
                  }
                  $elements_options[$group_key]['input:' . implode('|', $sub_element_key)] = $sub_element;
                }
              }
            }
          }
        }
        catch (\Exception $exception) {
          // Nothing to do.
        }
      }
    }

    return $elements_options;
  }

  /**
   * Prepare #options array for entity types.
   *
   * @return array
   *   The prepared array of entities and bundles.
   */
  protected function getEntityTypes() {
    $types = [];

    foreach ($this->entityTypeManager->getDefinitions() as $entity_id => $entity_type) {
      // Only allow content entities and ignore configuration entities.
      if ($entity_type instanceof ContentEntityTypeInterface) {
        if ($entity_type->getBundleEntityType() !== NULL) {
          foreach ($this->entityTypeBundleInfo->getBundleInfo($entity_id) as $bundle_id => $bundle_type) {
            $types[$entity_type->getLabel()->__toString()][$entity_id . ':' . $bundle_id] = $bundle_type['label'];
          }
        }
        else {
          $types[$entity_type->getLabel()->__toString()][$entity_id . ':' . $entity_id] = $entity_type->getLabel();
        }
      }
    }

    // Sort by entity type id.
    $type_keys = array_keys($types);
    array_multisort($type_keys, SORT_NATURAL, $types);

    return $types;
  }

  /**
   * Compose the form with the entity type fields.
   *
   * @param string $entity_type_bundle
   *   The entity type with its bundle.
   *
   * @return array
   *   The composed form with the entity type fields.
   */
  protected function getEntityFieldsForm($entity_type_bundle) {
    $form = [];

    [$type, $bundle] = explode(':', $entity_type_bundle);

    /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $properties */
    $fields = $this->entityFieldManager->getFieldDefinitions($type, $bundle);
    foreach ($fields as $field_name => $field) {
      $base_field = BaseFieldDefinition::create($field->getType());

      $field_properties = method_exists($field, 'getPropertyDefinitions') ? $field->getPropertyDefinitions() : $base_field->getPropertyDefinitions();
      if (empty($field_properties)) {
        $field_properties = $base_field->getPropertyDefinitions();
      }
      $field_schema = method_exists($field, 'getSchema') ? $field->getSchema() : $base_field->getSchema();

      // Use only properties with schema.
      if (!empty($field_schema['columns'])) {
        $field_properties = array_intersect_key($field_properties, $field_schema['columns']);
      }

      if (!empty($field_properties)) {
        $form[$field_name] = [
          '#type' => 'details',
          '#title' => $this->t('@label (Property: @name - Type: @type)', [
            '@label' => $field->getLabel(),
            '@name' => $field_name,
            '@type' => $field->getType(),
          ]),
          '#description' => $field->getDescription(),
          '#open' => FALSE,
          '#required' => $field->isRequired(),
          // @todo Validate if any child has value.
        ];

        foreach ($field_properties as $property_name => $property) {
          $form[$field_name][$property_name] = [
            '#type' => 'webform_select_other',
            '#title' => $this->t('Column: @name - Type: @type', [
              '@name' => $property->getLabel(),
              '@type' => $property->getDataType(),
            ]),
            '#description' => $property->getDescription(),
            '#options' => ['_null_' => $this->t('Null')] + $this->getElements() + [WebformSelectOther::OTHER_OPTION => $this->t('Custom value…')],
            '#default_value' => $this->configuration['entity_values'][$field_name][$property_name] ?? NULL,
            '#empty_value' => NULL,
            '#empty_option' => $this->t('- Select -'),
            '#parents' => [
              'settings',
              'entity_values',
              $field_name,
              $property_name,
            ],
            // @todo Use the property type.
            '#other__type' => 'textfield',
          ];
        }
        $form[$field_name]['webform_entity_handler_append'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('If checked, the value will be appended rather than overridden. Only apply updating an entity.'),
          '#default_value' => $this->configuration['entity_values'][$field_name]['webform_entity_handler_append'] ?? NULL,
          '#parents' => [
            'settings',
            'entity_values',
            $field_name,
            'webform_entity_handler_append',
          ],
          '#access' => $field->getFieldStorageDefinition()->getCardinality() != 1,
        ];
      }
    }

    // Remove the entity ID and bundle, they have theirs own settings.
    try {
      $entity_id_key = $this->entityTypeManager->getDefinition($type)->getKey('id');
      unset($form[$entity_id_key]);

      if ($this->entityTypeManager->getDefinition($type)->hasKey('bundle')) {
        unset($form[$this->entityTypeManager->getDefinition($type)->getKey('bundle')]);
      }
    }
    catch (\Exception $exception) {
      // Nothing to do.
    }

    return $form;
  }

}
