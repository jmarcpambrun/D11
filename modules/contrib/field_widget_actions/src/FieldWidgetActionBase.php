<?php

declare(strict_types=1);

namespace Drupal\field_widget_actions;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\SettingsCommand;
use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\WidgetInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The base class for FieldWidgetAction plugins.
 */
abstract class FieldWidgetActionBase extends PluginBase implements FieldWidgetActionInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The target property of the form element.
   */
  const FORM_ELEMENT_PROPERTY = 'value';

  /**
   * The widget plugin instance.
   *
   * @var \Drupal\Core\Field\WidgetInterface|null
   */
  protected ?WidgetInterface $widget = NULL;

  /**
   * The field definition.
   *
   * @var \Drupal\Core\Field\FieldDefinitionInterface|null
   */
  protected ?FieldDefinitionInterface $fieldDefinition = NULL;

  /**
   * Constructs FieldWidgetActionBase instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MessengerInterface $messenger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->messenger = $messenger;
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'enabled' => FALSE,
      'automatic' => FALSE,
      'button_label' => $this->getLabel(),
      'multiple' => $this->getMultiple(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return (string) $this->getPluginDefinition()['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return $this->getPluginDefinition()['description'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getWidgetTypes(): array {
    return $this->pluginDefinition['widget_types'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldTypes(): array {
    return $this->pluginDefinition['field_types'];
  }

  /**
   * {@inheritdoc}
   */
  public function getMultiple(): bool {
    return $this->pluginDefinition['multiple'] ?? TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, $action_id = NULL) {
    $element = [];
    $configuration = $this->getConfiguration();
    $multiple = $this->getFieldDefinition()->getFieldStorageDefinition()->isMultiple();
    $element['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $configuration['enabled'] ?? FALSE,
    ];
    $element['automatic'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Automatic'),
      '#description' => $this->t('When enabled, this action will be triggered automatically when the form loads instead of requiring a manual button click.'),
      '#default_value' => $configuration['automatic'] ?? FALSE,
      '#states' => [
        'visible' => [
          ':input[name*="[enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $element['button_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Button label'),
      '#default_value' => $configuration['button_label'] ?? '',
    ];
    $element['multiple'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Multiple'),
      '#description' => $this->t('If checked, an action button will appear for each item in the field. If not, an action will be performed for the entire field.'),
      '#default_value' => $configuration['multiple'] ?? $this->getMultiple(),
      '#access' => $multiple,
    ];
    $element['plugin_id'] = [
      '#type' => 'value',
      '#value' => $this->getPluginId(),
    ];
    $element['weight'] = [
      '#type' => 'hidden',
      '#default_value' => $configuration['weight'] ?? 0,
      '#attributes' => [
        'class' => ['field-widget-action-element-order-weight'],
      ],
    ];
    return $element;
  }

  /**
   * {@inheritDoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritDoc}
   */
  public function setConfiguration(array $configuration) {
    if (isset($configuration['weight'])) {
      $configuration['weight'] = (int) $configuration['weight'];
    }
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  /**
   * Build the entity.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The entity this field is attached to or NULL.
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    $entity = NULL;
    $form_object = $form_state->getFormObject();
    if ($form_object instanceof ContentEntityFormInterface) {
      // An empty multi-value widget (e.g. an unselected chosen_select) can
      // submit a literal NULL at its field's value path. core's
      // WidgetBase::extractFormValues() then passes that NULL to
      // massageFormValues(array), which throws a TypeError. Drop NULL field
      // values so extractFormValues() treats the field as absent — leaving it
      // empty — instead of crashing. Other shapes (e.g. a single-value
      // select's scalar) are normalized by element validators before this runs.
      $this->dropNullFieldValues($form, $form_state);
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $entity = $form_object->buildEntity($form, $form_state);
    }
    return $entity;
  }

  /**
   * Removes NULL field values from the submitted values before building.
   *
   * Scoped to the entity's own fields so the rest of the values tree is left
   * untouched; only values that are literally NULL are dropped (an empty array
   * or a populated value is left as-is).
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function dropNullFieldValues(array $form, FormStateInterface $form_state): void {
    $entity = $form_state->getFormObject()->getEntity();
    if (!$entity instanceof FieldableEntityInterface) {
      return;
    }
    $parents = $form['#parents'] ?? [];
    $values = $form_state->getValues();
    foreach (array_keys($entity->getFieldDefinitions()) as $field_name) {
      $path = array_merge($parents, [$field_name]);
      $key_exists = NULL;
      $value = NestedArray::getValue($values, $path, $key_exists);
      if ($key_exists && $value === NULL) {
        $form_state->unsetValue($path);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getWidget(): ?WidgetInterface {
    return $this->widget;
  }

  /**
   * {@inheritdoc}
   */
  public function setWidget(?WidgetInterface $widget): void {
    $this->widget = $widget;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinition(): ?FieldDefinitionInterface {
    return $this->fieldDefinition;
  }

  /**
   * {@inheritdoc}
   */
  public function setFieldDefinition(?FieldDefinitionInterface $fieldDefinition): void {
    $this->fieldDefinition = $fieldDefinition;
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool {
    return TRUE;
  }

  /**
   * Checks if the action is multiple.
   *
   * @return bool
   *   TRUE if the action is multiple, FALSE otherwise.
   */
  public function isMultiple(): bool {
    return $this->configuration['multiple'] ?? $this->getMultiple();
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries(): array {
    return [];
  }

  /**
   * Gets the button label.
   *
   * @return string
   *   The button label.
   */
  public function getButtonLabel(): string {
    return $this->configuration['button_label'] ?: $this->getLabel();
  }

  /**
   * {@inheritdoc}
   */
  public function getAjaxCallback(): ?string {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function completeFormAlter(array &$form, FormStateInterface $form_state, array $context = []) {
    /** @var \Drupal\field\Entity\FieldConfig $field_definition */
    $field_definition = $context['items']->getFieldDefinition();
    if ($this->getAjaxCallback()) {
      if (!empty($form['widget'][0]['#group'])) {
        $form['widget']['#process'][] = [$this, 'processWidgetWithGroup'];
      }
      else {
        // Add wrapper.
        $prefix = $form['#prefix'] ?? '';
        $suffix = $form['#suffix'] ?? '';
        $form['#prefix'] = '<div id="field-widget-action-' . $field_definition->getName() . '" class="field-widget-action-element-wrapper">' . $prefix;
        $form['#suffix'] = $suffix . '</div>';
        $form['#attributes']['class'][] = 'field-widget-action-element';
      }
    }
    if (!$this->isMultiple()) {
      $this->actionButton($form, $form_state, $context);
    }
  }

  /**
   * Process the element with #group property.
   *
   * @param array $element
   *   The given element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $form
   *   The complete form.
   *
   * @return array
   *   The processed element.
   */
  public function processWidgetWithGroup(array $element, FormStateInterface $form_state, array &$form) {
    if (!empty($element[0]['#group'])) {
      $group = $element[0]['#group'];
      // Add wrapper.
      $prefix = $form[$group]['#prefix'] ?? '';
      $suffix = $form[$group]['#suffix'] ?? '';
      $form[$group]['#prefix'] = '<div id="field-widget-action-' . $group . '" class="field-widget-action-element-wrapper">' . $prefix;
      $form[$group]['#suffix'] = $suffix . '</div>';
      $form[$group]['#attributes']['class'][] = 'field-widget-action-element';
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function singleElementFormAlter(array &$form, FormStateInterface $form_state, array $context = []) {
    if ($this->isMultiple()) {
      $this->actionButton($form, $form_state, $context);
    }
  }

  /**
   * Returns the action button widget ID.
   *
   * @param string $fieldName
   *   The field name.
   * @param array $context
   *   The context.
   *
   * @return string
   *   The Widget ID.
   */
  protected function getActionButtonWidgetId(string $fieldName, array $context): string {
    if (!empty($context['action_id'])) {
      $widgetId = $context['action_id'];
    }
    else {
      $widgetId = $fieldName . '_field_widget_action_' . $this->getPluginId();
    }
    if (!empty($context['delta'])) {
      $widgetId .= '_' . $context['delta'];
    }
    return $widgetId;
  }

  /**
   * Returns the action button depending on the `multiple` value of definition.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param array $context
   *   The context.
   */
  protected function actionButton(array &$form, FormStateInterface $form_state, array $context = []) {
    $fieldName = $context['items']->getFieldDefinition()->getName();
    $widgetId = $this->getActionButtonWidgetId($fieldName, $context);
    $weight = $this->configuration['weight'] ?? 0;
    $automatic = !empty($this->configuration['automatic']);
    $form[$widgetId] = [
      '#type' => 'button',
      '#value' => $this->getButtonLabel(),
      '#weight' => $weight + 10,
      '#name' => $widgetId,
      '#attributes' => [
        'class' => [
          'button--secondary',
          'button--small',
          'field-widget-action-widget-button',
          'field-widget-action-' . $this->getPluginId(),
        ],
        'data-wrapper-id' => 'field-widget-action-' . $fieldName,
        'data-widget-id' => $this->getPluginId(),
        'data-widget-field' => $fieldName,
        'data-widget-delta' => $context['delta'] ?? '',
      ],
      '#field_widget_action_field_name' => $fieldName,
      // When called from hook_field_widget_complete_form, delta is not present.
      '#field_widget_action_field_delta' => $context['delta'] ?? NULL,
      '#field_widget_action_settings' => $this->getConfiguration(),
    ];
    if ($automatic) {
      $form[$widgetId]['#attributes']['data-fwa-automatic'] = 'true';
    }
    $form[$widgetId]['#attached'] = [
      'library' => array_merge($this->getLibraries(), ['field_widget_actions/widget_button']),
    ];
    if ($this->getAjaxCallback()) {
      // If form element is inside group ajax should reload the whole parent
      // container.
      if (!empty($form['#group'])) {
        $fieldName = $form['#group'];
      }
      $form[$widgetId]['#ajax'] = [
        'callback' => [$this, $this->getAjaxCallback()],
        'wrapper' => 'field-widget-action-' . $fieldName,
        'prevent' => 'submit',
      ];
      // A button-level #validate array replaces the form-level one entirely
      // (see FormBuilder::doBuildForm()), so '::validateForm' must be
      // included explicitly here or the entity (and thus genuine field
      // constraints such as an invalid email format) never gets validated at
      // all on this request. Suppress required-field errors in a #validate
      // handler (runs after element validators have normalized values,
      // before submit handlers) so that clicking Generate never surfaces
      // required-field violations for fields the user has not filled in yet,
      // while submit handlers (e.g. AutomatorBaseAction::runAutomatorSubmit)
      // still fire. Genuine validation errors are preserved — see
      // clearErrorsForAction().
      $form[$widgetId]['#validate'] = ['::validateForm', [$this, 'clearErrorsForAction']];
    }
  }

  /**
   * Form validate handler attached to every action button.
   *
   * Drupal calls button-level #validate handlers instead of the form-level
   * ones when that button triggers the form, and it calls them AFTER all
   * element validators have run (so single-cardinality selects are already
   * normalized) and AFTER required-field errors have been collected — but
   * BEFORE the submit phase, and before errors are flushed to the messenger.
   *
   * The action button is not a real submission: the user asked to generate ONE
   * field, not to save the entity. This suppresses only "field is required"
   * errors for fields they have not filled in yet, but preserves every other
   * validation error (malformed value, out-of-range, custom constraint, …). A
   * blanket clear would let submit handlers (e.g.
   * AutomatorBaseAction::runAutomatorSubmit) run on genuinely invalid data and
   * silently discard real problems. Because required-but-empty errors are
   * removed here, before FormErrorHandler converts errors to messages, they
   * never reach the messenger, while genuine errors still surface as usual.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function clearErrorsForAction(array &$form, FormStateInterface $form_state): void {
    $required_keys = [];
    $this->collectRequiredButEmptyKeys($form, $required_keys);

    $errors = $form_state->getErrors();
    $form_state->clearErrors();
    // Re-record every error that is not a required-but-empty violation. Widen
    // the limit so each preserved error is actually recorded.
    $form_state->setLimitValidationErrors(NULL);
    foreach ($errors as $name => $message) {
      if (!in_array($name, $required_keys, TRUE)) {
        $form_state->setErrorByName($name, $message);
      }
    }
  }

  /**
   * Collects the error keys of required fields the user left unfilled.
   *
   * The error key is implode('][', #parents), matching how core keys form
   * errors. See isUnfilledRequiredElement() for what counts as unfilled.
   *
   * @param array $element
   *   The form element to scan (recursively).
   * @param array $keys
   *   The collected error keys, by reference.
   */
  protected function collectRequiredButEmptyKeys(array $element, array &$keys): void {
    if (isset($element['#parents']) && $this->isUnfilledRequiredElement($element)) {
      $keys[] = implode('][', $element['#parents']);
    }
    foreach (Element::children($element) as $child) {
      if (is_array($element[$child] ?? NULL)) {
        $this->collectRequiredButEmptyKeys($element[$child], $keys);
      }
    }
  }

  /**
   * Determines whether an element is a required field left empty by the user.
   *
   * An error on such an element is a "field is required" violation that must
   * be suppressed on the action path (the user has not filled it in yet). An
   * error on a required element that DOES hold a value is a genuine value
   * problem and must be preserved.
   *
   * @param array $element
   *   The form element.
   *
   * @return bool
   *   TRUE if the element is required and unfilled.
   */
  protected function isUnfilledRequiredElement(array $element): bool {
    // Core flags required-but-empty elements during validation. This handles
    // text fields, checkboxes, the '0' edge case, and empty multi-value
    // selects.
    if (!empty($element['#required_but_empty'])) {
      return TRUE;
    }
    // Options widgets (single select, radios) run their own required check in
    // an #element_validate handler and set the error without that flag; an
    // unselected value is the '_none' marker. Compound elements (e.g. date/
    // time or autocomplete widgets) can also be #required with an array
    // #value, but their entries are not scalars to compare against the
    // marker, so only apply this check when every entry is scalar.
    if (!empty($element['#required']) && array_key_exists('#value', $element)) {
      $value = $element['#value'];
      if ($value === '_none') {
        return TRUE;
      }
      if (is_array($value) && count(array_filter($value, 'is_scalar')) === count($value) && !array_diff($value, [
        '_none', '',
      ])) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Returns the target css selector for suggestions.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return string
   *   The css selector to fill in with the selected suggestion.
   */
  protected function getSuggestionsTarget(array &$form, FormStateInterface $form_state): string {
    $target_element = $this->getTargetElement($form, $form_state);
    if (!$target_element) {
      return '';
    }

    // Safely try data-drupal-selector, fallback to id, or return empty.
    return $target_element['#attributes']['data-drupal-selector']
      ?? $target_element['#id']
      ?? '';
  }

  /**
   * Returns the target form element this action is attached to.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form element.
   */
  protected function getTargetElement(array &$form, FormStateInterface $form_state): array {
    // Get the triggering element, the button is inside the same field widget.
    $triggering_element = $form_state->getTriggeringElement();
    $delta = $this->getTargetElementDelta($form, $form_state);
    $array_parents = $triggering_element['#array_parents'];

    // Remove the button key.
    array_pop($array_parents);

    // Identify the correct property name (default to 'value').
    if ($delta !== NULL) {
      $array_parents[] = static::FORM_ELEMENT_PROPERTY;
    }
    // Force the first element when no delta is present.
    else {
      $array_parents = [
        $this->getTargetElementFieldName($form, $form_state),
        'widget',
        0,
        static::FORM_ELEMENT_PROPERTY,
      ];
    }
    return NestedArray::getValue($form, $array_parents) ?? [];
  }

  /**
   * Gets the delta of form element.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return int|null
   *   The delta of the form element or null if it is attached to the complete
   *   widget form.
   */
  protected function getTargetElementDelta(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    return $triggering_element['#field_widget_action_field_delta'] ?? NULL;
  }

  /**
   * Gets the field name that corresponds to form element.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return string
   *   The field name for the form element.
   */
  protected function getTargetElementFieldName(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    return $triggering_element['#field_widget_action_field_name'] ?? '';
  }

  /**
   * Returns suggestions in a dialog.
   *
   * @param array|string $suggestions
   *   The content to display in a dialog.
   * @param string $selector
   *   The selector for inserting a suggestion.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response object.
   */
  protected function returnSuggestions(array|string $suggestions, $selector = '') {
    $message = '';
    // If it is empty string or empty array, no suggestions were actually
    // provided, so the dialog should not show anything selectable.
    if (!empty($suggestions)) {
      if (!is_array($suggestions)) {
        $suggestions = [$suggestions];
      }
      $message = [
        '#theme' => 'field_widget_actions_suggestions',
        '#suggestions' => $suggestions,
        '#attached' => [
          'library' => [
            'field_widget_actions/suggestions',
          ],
        ],
      ];
    }

    $response = new AjaxResponse();
    // Collect all messages emitted so far. In case of validation errors we need
    // to display them as well right away.
    foreach ($this->messenger->all() as $type => $items) {
      foreach ($items as $item) {
        $response->addCommand(new MessageCommand($item, NULL, ['type' => $type]));
      }
    }
    // Remove all messages, as they will be displayed with ajax commands.
    $this->messenger->deleteAll();
    if (!empty($selector)) {
      $response->addCommand(new SettingsCommand(['fwa_suggestion_target' => ['target' => $selector]], TRUE));
    }
    if (empty($message)) {
      $message = $this->t('Unfortunately no suggestions were provided.');
    }
    $response->addCommand(new OpenModalDialogCommand($this->t('Suggestions'), $message, [
      'width' => '80%',
      'dialogClass' => 'ui-dialog-fwa-suggestions',
    ]));
    return $response;
  }

}
