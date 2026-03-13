<?php

namespace Drupal\webform_entity_view\Plugin\WebformElement;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformElement\WebformMarkupBase;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'webform_entity_view' element.
 *
 * @WebformElement(
 *   id = "webform_entity_view",
 *   label = @Translation("Entity View"),
 *   description = @Translation("Provides an element to render an entity."),
 *   category = @Translation("Entity reference elements"),
 *   states_wrapper = TRUE,
 * )
 */
class WebformEntityView extends WebformMarkupBase {

  use DependencySerializationTrait;

  /**
   * The entity display repository manager.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface|null
   */
  protected $entityDisplayRepository;

  /**
   * The entity type repository manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeRepositoryInterface|null
   */
  protected $entityTypeRepository;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfo
   */
  protected $entityTypeBundleInfo;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityDisplayRepository = $container->get('entity_display.repository');
    $instance->entityTypeBundleInfo = $container->get('entity_type.bundle.info');
    $instance->entityTypeRepository = $container->get('entity_type.repository');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineDefaultProperties() {
    $properties = parent::defineDefaultProperties() + [
      'title' => '',
      'title_display' => '',
      'target_type' => '',
      'target_bundle' => '',
      'view_mode' => '',
      'selected_entity' => '',
    ];
    unset($properties['markup']);
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    // Hide/Remove default value, form display and markup form elements.
    unset($form['markup'], $form['default']);
    $form['form']['#access'] = FALSE;

    if ($form_state->isRebuilding()) {
      // Get entity reference value from user input because
      // $form_state->getValue() does not always contain every input's value.
      $user_input = $form_state->getUserInput();
      $target_type = (!empty($user_input['properties']['target_type'])) ? $user_input['properties']['target_type'] : '';
      $target_bundle = (!empty($user_input['properties']['target_bundle'])) ? $user_input['properties']['target_bundle'] : '';
    }
    else {
      $element_properties = $form_state->get('element_properties');
      $target_type = $element_properties['target_type'] ?? '';
      $target_bundle = $element_properties['target_bundle'] ?? '';
    }

    $form['entity'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Entity settings'),
      '#prefix' => '<div id="webform-entity-view--entity-settings">',
      '#suffix' => '</div>',
    ];
    $form['entity']['display_on'] = [
      '#type' => 'select',
      '#title' => $this->t('Display on'),
      '#options' => $this->getDisplayOnOptions(),
    ];
    $form['entity']['target_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type of item to view'),
      '#options' => $this->entityTypeRepository->getEntityTypeLabels(TRUE),
      '#default_value' => $target_type ?? '',
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [get_called_class(), 'updateEntitySettings'],
        'wrapper' => 'webform-entity-view--entity-settings',
      ],
    ];
    $form['entity']['target_bundle'] = [
      '#type' => 'select',
      '#options' => !empty($target_type) ? $this->bundleOptions($target_type) : [],
      '#title' => $this->t('Bundle'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [get_called_class(), 'updateEntitySettings'],
        'wrapper' => 'webform-entity-view--entity-settings',
      ],
    ];

    if ($target_type) {
      $form['entity']['selected_entity'] = [
        '#type' => 'entity_autocomplete',
        '#target_type' => $target_type,
        '#selection_handler' => 'default',
        '#selection_settings' => [
          'target_bundles' => [$target_bundle],
        ],
        '#title' => $this->t('Entity'),
        '#required' => TRUE,
      ];
    }

    $form['entity']['view_mode'] = [
      '#type' => 'select',
      '#options' => $this->entityDisplayRepository->getViewModeOptions($target_type),
      '#title' => $this->t('View mode'),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * Ajax callback for the "Entity settings" options form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The properties element.
   */
  public static function updateEntitySettings(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $array_parents = array_slice($triggering_element['#array_parents'], 0, -1);
    return NestedArray::getValue($form, $array_parents);
  }

  /**
   * {@inheritdoc}
   */
  public function prepare(array &$element, WebformSubmissionInterface $webform_submission = NULL) {
    try {
      /** @var \Drupal\Core\Entity\EntityInterface $entity */
      if (!empty($element['#selected_entity']) && ($entity = $this->entityTypeManager->getStorage($element['#target_type'])->load($element['#selected_entity']))) {
        $element['#type'] = 'container';
        $element['#field_type'] = 'webform_entity_view';
        $view_builder = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId());
        $current_language = $webform_submission->getLangcode();
        $entity_view_language = $entity->hasTranslation($current_language) ? $current_language : $entity->language()->getId();
        $element[] = $view_builder->view($entity, $element['#view_mode'], $entity_view_language);

        parent::prepare($element, $webform_submission);
      }
    }
    catch (\Exception $exception) {
      \Drupal\Component\Utility\DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '10.1.0', fn() => \Drupal\Core\Utility\Error::logException(\Drupal::logger('webform_entity_view'), $exception), fn() => watchdog_exception('webform_entity_view', $exception));
      // Disallow displaying the element in case of error.
      $element['#access'] = FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFormElementClassDefinition() {
    $definition = $this->elementInfo->getDefinition('webform_markup');
    return $definition['class'];
  }

  /**
   * Generate bundle options based on the entity type.
   *
   * @param string $target_type
   *   The entity target type.
   *
   * @return array
   *   Array of options for use in the form element.
   */
  protected function bundleOptions($target_type) {
    $bundles = $this->entityTypeBundleInfo->getBundleInfo($target_type);
    return array_map(function ($bundle) {
      return $bundle['label'];
    }, $bundles);
  }

}
