<?php

namespace Drupal\triples_field\Plugin\Field\FieldWidget;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldFilteredMarkup;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Email;
use Drupal\Core\Utility\Token;
use Drupal\triples_field\Plugin\Field\FieldType\TriplesField as FieldItem;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Plugin implementation of the 'triples_field' widget.
 *
 * @FieldWidget(
 *   id = "triples_field_table",
 *   label = @Translation("Triple Field Table"),
 *   field_types = {"triples_field"}
 * )
 */
class TriplesFieldTable extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, protected ConfigFactoryInterface $configFactory, protected Token $token) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new self(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('config.factory'),
      $container->get('token'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state):array {
    $field_settings = $this->getFieldSettings();
    $settings = $this->getSettings();
    $subfields = array_keys($field_settings["storage"]);
    $widget = [
      '#type' => 'table',
      '#title' => $element["#title"],
    ];
    $isHeader = FALSE;
    $header = [];
    foreach ($subfields as $subfield) {
      if (!$isHeader && !empty($field_settings[$subfield]["label"])) {
        $isHeader = TRUE;
      }
      $header[] = $field_settings[$subfield]["label"];
      $widget[0][$subfield] = [
        '#type' => $settings[$subfield]['type'],
        '#default_value' => $items[$delta]->{$subfield} ?: NULL,
        '#subfield_settings' => $settings[$subfield],
        '#wrapper_attributes' => ['class' => [Html::getId('triples_field-subfield-form-item')]],
      ];

      $widget_type = $settings[$subfield]['type'];
      $storage_type = $field_settings['storage'][$subfield]['type'];

      switch ($widget_type) {

        case 'textfield':
        case 'email':
        case 'tel':
        case 'url':
          // Find out appropriate max length fot the element.
          $max_length_map = [
            'string' => $field_settings['storage'][$subfield]['maxlength'],
            'telephone' => $field_settings['storage'][$subfield]['maxlength'],
            'email' => Email::EMAIL_MAX_LENGTH,
            'uri' => 2048,
          ];
          if (!empty($max_length_map[$storage_type])) {
            $widget[0][$subfield]['#maxlength'] = $max_length_map[$storage_type];
          }
          $widget[0][$subfield]['#size'] = $settings[$subfield]['size'] ?? $widget[0][$subfield]['#size'] ?? NULL;
          $widget[0][$subfield]['#placeholder'] = $settings[$subfield]['placeholder'] ?? $widget[0][$subfield]['#placeholder'] ?? NULL;
          break;

        case 'checkbox':
          $widget[0][$subfield]['#title'] = $settings[$subfield]['label'];
          break;

        case 'select':
          $label = $field_settings[$subfield]['required'] ? $this->t('- Select a value -') : $this->t('- None -');
          $widget[0][$subfield]['#options'] = ['' => $label];
          if ($field_settings[$subfield]['list']) {
            $widget[0][$subfield]['#options'] += $field_settings[$subfield]['allowed_values'];
          }
          break;

        case 'radios':
          $label = $field_settings[$subfield]['required'] ? $this->t('N/A') : $this->t('- None -');
          $widget[0][$subfield]['#options'] = ['' => $label];
          if ($field_settings[$subfield]['list']) {
            $widget[0][$subfield]['#options'] += $field_settings[$subfield]['allowed_values'];
          }
          break;

        case 'textarea':
          if (!empty($settings[$subfield]['rows'])) {
            $widget[0][$subfield]['#rows'] = $settings[$subfield]['rows'];
          }
          if (!empty($settings[$subfield]['placeholder'])) {
            $widget[0][$subfield]['#placeholder'] = $settings[$subfield]['placeholder'];
          }
          if (!empty($settings[$subfield]['editor'])) {
            $value_element = $widget[0][$subfield];
            $widget[0][$subfield] = [];
            $widget[0][$subfield]['#default_value'] = $value_element['#default_value'];
            $widget[0][$subfield]['value'] = $value_element;
            $widget[0][$subfield]['#type'] = 'text_format';
            $widget[0][$subfield]['#format'] = $items[$delta]->{$subfield . '_format'} ?? NULL;
            $widget[0][$subfield]['#base_type'] = 'textarea';
          }
          break;

        case 'number':
        case 'range':
          if (in_array($storage_type, ['integer', 'float', 'numeric'])) {
            if ($field_settings[$subfield]['min']) {
              $widget[0][$subfield]['#min'] = $field_settings[$subfield]['min'];
            }
            if ($field_settings[$subfield]['max']) {
              $widget[0][$subfield]['#max'] = $field_settings[$subfield]['max'];
            }
            if ($storage_type == 'numeric') {
              $widget[0][$subfield]['#step'] = pow(0.1, $field_settings['storage'][$subfield]['scale']);
            }
            elseif ($storage_type == 'float') {
              $widget[0][$subfield]['#step'] = 'any';
            }
          }
          break;

        case 'datetime':
          $widget[0][$subfield]['#default_value'] = $items[$delta]->createDate($subfield);
          if ($field_settings['storage'][$subfield]['datetime_type'] == 'date') {
            $widget[0][$subfield]['#date_time_element'] = 'none';
            $widget[0][$subfield]['#date_time_format'] = '';
          }
          else {
            if (!empty($widget[0][$subfield]['#default_value'])) {
              $widget[0][$subfield]['#default_value']->setTimezone(new \DateTimezone(date_default_timezone_get()));
            }
            // Ensure that the datetime field processing doesn't set its own
            // time zone here.
            $widget[0][$subfield]['#date_timezone'] = date_default_timezone_get();
          }
          break;
      }
    }
    if (!empty($isHeader)) {
      $widget['#header'] = $header;
    }
    return $element + $widget;
  }

  /**
   * {@inheritdoc}
   */
  public function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
    $field_name = $this->fieldDefinition->getName();
    $cardinality = $this->fieldDefinition->getFieldStorageDefinition()
      ->getCardinality();
    $parents = $form['#parents'];
    switch ($cardinality) {
      case FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED:
        $field_state = static::getWidgetState($parents, $field_name, $form_state);
        $max = $field_state['items_count'];
        $is_multiple = TRUE;
        break;

      default:
        $max = $cardinality - 1;
        $is_multiple = ($cardinality > 1);
        break;
    }

    $title = $this->fieldDefinition->getLabel();
    $description = FieldFilteredMarkup::create($this->token->replace($this->fieldDefinition->getDescription()));
    $header = [''];
    $field_settings = $this->getFieldSettings();
    $subfields = array_keys($field_settings["storage"]);
    $order_class = $field_name . '-delta-order';
    foreach ($subfields as $subfield) {
      $header[] = $field_settings[$subfield]["label"];
    }
    $elements = [];
    for ($delta = 0; $delta <= $max; $delta++) {
      if (!isset($items[$delta])) {
        $items->appendItem();
      }
      if ($is_multiple) {
        $element = [
          '#title' => $this->t('@title (value @number)',
            ['@title' => $title, '@number' => $delta + 1]),
          '#title_display' => 'invisible',
          '#description' => '',
        ];
      }
      else {
        $element = [
          '#title' => $title,
          '#title_display' => 'before',
          '#description' => $description,
        ];
      }
      $element = $this->formSingleElement($items, $delta, $element, $form, $form_state);
      if ($element) {
        $element[0]['#attributes']['class'][] = 'draggable';
        $element[0]['#weight'] = $items[$delta]->_weight ?: $delta;
        $element[0]['_weight'] = [
          '#type' => 'weight',
          '#title' => $this->t('Weight for row @number', ['@number' => $delta + 1]),
          '#title_display' => 'invisible',
          '#delta' => $max,
          '#attributes' => ['class' => [$order_class]],
          '#default_value' => $items[$delta]->_weight ?: $delta,
          '#weight' => 100,
        ];

        array_unshift($element[0], ['#markup' => '']);
        $elements[$delta] = $element[0];
      }
    }
    if (!empty($elements)) {
      $elements += [
        '#theme' => 'field_multiple_value_form',
        // '#type' => 'table',
        '#header' => $header,
        '#field_name' => $field_name,
        '#cardinality' => $cardinality,
        '#cardinality_multiple' => $this->fieldDefinition->getFieldStorageDefinition()->isMultiple(),
        '#required' => $this->fieldDefinition->isRequired(),
        '#title' => $title,
        '#description' => $description,
        '#max_delta' => $max,
        '#widgetTable' => TRUE,
        '#empty' => $this->t('There are no widgets.'),
        '#tabledrag' => [
          [
            'action' => 'order',
            'relationship' => 'sibling',
            'group' => $order_class,
          ],
        ],
      ];
      $header[0] = [
        'data' => [
          '#markup' => $header[0],
        ],
        'class' => ['field-label'],
      ];
      $header[] = $this->t('Order', [], ['context' => 'Sort order']);
      $elements['#header'] = $header;
      // Add an "add more" button if it doesn't work with a programmed form.
      if ($cardinality == FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED && !$form_state->isProgrammed()) {
        $id_prefix = implode('-', array_merge($parents, [$field_name]));
        $wrapper_id = Html::getUniqueId($id_prefix . '-add-more-wrapper');
        $elements['#prefix'] = '<div id="' . $wrapper_id . '">';
        $elements['#suffix'] = '</div>';
        $elements['add_more'] = [
          '#type' => 'submit',
          '#name' => strtr($id_prefix, '-', '_') . '_add_more',
          '#value' => $this->t('Add another item'),
          '#attributes' => [
            'class' => ['field-add-more-submit', 'out-of-table'],
            'data-ajaxTo' => $wrapper_id,
          ],
          '#wrapper_attributes' => ['colspan' => count($header)],
          '#limit_validation_errors' => [array_merge($parents, [$field_name])],
          '#submit' => [[get_class($this), 'addMoreSubmit']],
          '#ajax' => [
            'callback' => [get_class($this), 'addMoreAjax'],
            'wrapper' => $wrapper_id,
            'effect' => 'fade',
          ],
          '#element_parents' => $parents,
        ];
      }
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    $configs = \Drupal::config('triples_field.settings');
    $settings = [];
    $subfields = array_keys($configs->get('fields'));
    foreach ($subfields as $subfield) {
      $settings[$subfield] = [
        // As this method is static there is no way to set an appropriate type
        // for the sub-widget. Let self::getSettings() do it instead.
        'type' => NULL,
        'label_display' => 'block',
        'size' => 30,
        'placeholder' => '',
        'label' => t('Ok'),
        'cols' => 10,
        'rows' => 5,
      ];
    }

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {

    $settings = $this->getSettings();
    $field_settings = $this->getFieldSettings();

    $types = FieldItem::subfieldTypes();

    $field_name = $this->fieldDefinition->getName();

    $configs = $this->configFactory->get('triples_field.settings');
    $subfields = array_keys($titles = $configs->get('fields'));
    $element = [];
    foreach ($subfields as $subfield) {

      $type = $field_settings['storage'][$subfield]['type'];

      $title = $titles[$subfield];
      $title .= ' - ' . $types[$type];

      $element[$subfield] = [
        '#type' => 'details',
        '#title' => $title,
        '#open' => FALSE,
      ];

      $element[$subfield]['type'] = [
        '#type' => 'select',
        '#title' => $this->t('Widget'),
        '#default_value' => $settings[$subfield]['type'],
        '#required' => TRUE,
        '#options' => $this->getSubwidgets($type, $field_settings[$subfield]['list']),
      ];

      $options = [
        'block' => $this->t('Above'),
        'inline' => $this->t('Inline'),
        'invisible' => $this->t('Invisible'),
        'hidden' => $this->t('Hidden'),
      ];
      if ($settings[$subfield]['type'] == 'datetime') {
        unset($options['inline']);
        unset($options['invisible']);
      }
      $element[$subfield]['label_display'] = [
        '#type' => 'select',
        '#title' => $this->t('Label'),
        '#default_value' => $settings[$subfield]['label_display'] ?? '',
        '#options' => $options,
        '#access' => static::isLabelSupported($settings[$subfield]['type']),
      ];
      $type_selector = "select[name='fields[$field_name][settings_edit_form][settings][$subfield][type]'";
      $element[$subfield]['size'] = [
        '#type' => 'number',
        '#title' => $this->t('Size'),
        '#default_value' => $settings[$subfield]['size'] ?? '',
        '#min' => 1,
        '#states' => [
          'visible' => [
            [$type_selector => ['value' => 'textfield']],
            [$type_selector => ['value' => 'email']],
            [$type_selector => ['value' => 'tel']],
            [$type_selector => ['value' => 'url']],
          ],
        ],
      ];

      $element[$subfield]['placeholder'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Placeholder'),
        '#default_value' => $settings[$subfield]['placeholder'] ?? '',
        '#states' => [
          'visible' => [
            [$type_selector => ['value' => 'textfield']],
            [$type_selector => ['value' => 'textarea']],
            [$type_selector => ['value' => 'email']],
            [$type_selector => ['value' => 'tel']],
            [$type_selector => ['value' => 'url']],
          ],
        ],
      ];

      $element[$subfield]['label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Label'),
        '#default_value' => $settings[$subfield]['label'] ?? '',
        '#states' => [
          'visible' => [$type_selector => ['value' => 'checkbox']],
        ],
      ];

      $element[$subfield]['cols'] = [
        '#type' => 'number',
        '#title' => $this->t('Columns'),
        '#default_value' => $settings[$subfield]['cols'] ?? '',
        '#min' => 1,
        '#description' => $this->t('How many columns wide the textarea should be'),
        '#states' => [
          'visible' => [$type_selector => ['value' => 'textarea']],
        ],
      ];

      $element[$subfield]['rows'] = [
        '#type' => 'number',
        '#title' => $this->t('Rows'),
        '#default_value' => $settings[$subfield]['rows'] ?? '',
        '#min' => 1,
        '#description' => $this->t('How many rows high the textarea should be.'),
        '#states' => [
          'visible' => [$type_selector => ['value' => 'textarea']],
        ],
      ];

    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $settings = $this->getSettings();
    $field_settings = $this->getFieldSettings();

    $subfield_types = FieldItem::subfieldTypes();

    $summary = [];

    $configs = $this->configFactory->get('triples_field.settings');

    $subfields = array_keys($titles = $configs->get('fields'));
    foreach ($subfields as $subfield) {
      $subfield_type = $subfield_types[$field_settings['storage'][$subfield]['type']];

      $summary[] = new FormattableMarkup(
        '<b>@subfield - @subfield_type</b>',
        [
          '@subfield' => $titles[$subfield],
          '@subfield_type' => strtolower($subfield_type),
        ]
      );

      $summary[] = $this->t('Widget: @type', ['@type' => $settings[$subfield]['type']]);
      if (static::isLabelSupported($settings[$subfield]['type']) && !empty($settings[$subfield]['label_display'])) {
        $summary[] = $this->t('Label display: @label', ['@label' => $settings[$subfield]['label_display']]);
      }
      switch ($settings[$subfield]['type']) {
        case 'textfield':
        case 'email':
        case 'tel':
        case 'url':
          $summary[] = $this->t('Size: @size', ['@size' => $settings[$subfield]['size'] ?? '']);
          if (!empty($settings[$subfield]['placeholder'])) {
            $summary[] = $this->t('Placeholder: @placeholder', ['@placeholder' => $settings[$subfield]['placeholder'] ?? '']);
          }
          break;

        case 'checkbox':
          $summary[] = $this->t('Label: @label', ['@label' => $settings[$subfield]['label']]);
          break;

        case 'select':
          break;

        case 'textarea':
          $summary[] = $this->t('Columns: @cols', ['@cols' => $settings[$subfield]['cols'] ?? '']);
          $summary[] = $this->t('Rows: @rows', ['@rows' => $settings[$subfield]['rows'] ?? '']);
          if (!empty($settings[$subfield]['placeholder'])) {
            $summary[] = $this->t('Placeholder: @placeholder', ['@placeholder' => $settings[$subfield]['placeholder']]);
          }
          break;
      }

    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state): array {
    $storage_settings = $this->getFieldSetting('storage');
    $subfields = array_keys($storage_settings);
    foreach ($values as $delta => $value) {
      foreach ($subfields as $subfield) {
        if (is_array($value[$subfield])) {
          foreach ($value[$subfield] as $key => $field_value) {
            if ($key == 'value') {
              $values[$delta][$subfield] = $field_value;
            }
            else {
              $values[$delta][$subfield . '_' . $key] = $field_value;
            }
          }
        }
        elseif ($value[$subfield] === '') {
          $values[$delta][$subfield] = NULL;
        }
        elseif ($value[$subfield] instanceof DrupalDateTime) {
          $date = $value[$subfield];

          $storage_format = $storage_settings[$subfield]['datetime_type'] == 'datetime'
            ? FieldItem::DATETIME_DATETIME_STORAGE_FORMAT
            : FieldItem::DATETIME_DATE_STORAGE_FORMAT;

          // Before it can be saved, the time entered by the user must be
          // converted to the storage time zone.
          $storage_timezone = new \DateTimezone(FieldItem::DATETIME_STORAGE_TIMEZONE);
          $values[$delta][$subfield] = $date->setTimezone($storage_timezone)->format($storage_format);
        }
      }
    }

    return $values;
  }

  /**
   * Returns available subwidgets.
   */
  public function getSubwidgets(string $subfield_type, bool $list): array {
    $subwidgets = [];

    if ($list) {
      $subwidgets['select'] = $this->t('Select list');
      $subwidgets['radios'] = $this->t('Radio buttons');
    }

    switch ($subfield_type) {

      case 'boolean':
        $subwidgets['checkbox'] = $this->t('Checkbox');
        break;

      case 'string':
        $subwidgets['textfield'] = $this->t('Textfield');
        $subwidgets['email'] = $this->t('Email');
        $subwidgets['tel'] = $this->t('Telephone');
        $subwidgets['url'] = $this->t('Url');
        $subwidgets['color'] = $this->t('Color');
        break;

      case 'email':
        $subwidgets['email'] = $this->t('Email');
        $subwidgets['textfield'] = $this->t('Textfield');
        break;

      case 'telephone':
        $subwidgets['tel'] = $this->t('Telephone');
        $subwidgets['textfield'] = $this->t('Textfield');
        break;

      case 'uri':
        $subwidgets['url'] = $this->t('Url');
        $subwidgets['textfield'] = $this->t('Textfield');
        break;

      case 'text':
      case 'text_long':
        $subwidgets['textarea'] = $this->t('Text area');
        break;

      case 'integer':
      case 'float':
      case 'numeric':
        $subwidgets['number'] = $this->t('Number');
        $subwidgets['textfield'] = $this->t('Textfield');
        $subwidgets['range'] = $this->t('Range');
        break;

      case 'datetime_iso8601':
        $subwidgets['datetime'] = $this->t('Date');
        break;

    }

    return $subwidgets;
  }

  /**
   * {@inheritdoc}
   */
  public function errorElement(array $element, ConstraintViolationInterface $violation, array $form, FormStateInterface $form_state) {
    /* @noinspection PhpUndefinedFieldInspection */
    // @see https://www.drupal.org/project/drupal/issues/2600790
    return isset($violation->arrayPropertyPath[0]) ? $element[$violation->arrayPropertyPath[0]] : $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function getFieldSettings(): array {
    $field_settings = parent::getFieldSettings();
    $subfields = array_keys($field_settings["storage"]);
    foreach ($subfields as $subfield) {
      $subfield_type = $field_settings['storage'][$subfield]['type'];
      if ($field_settings[$subfield]['list'] && !FieldItem::isListAllowed($subfield_type)) {
        $field_settings[$subfield]['list'] = FALSE;
      }
    }

    return $field_settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings(): array {
    $settings = [];
    $field_settings = $this->getFieldSettings();
    $subfields = array_keys($field_settings["storage"]);
    foreach ($subfields as $subfield) {
      $widget_types = $this->getSubwidgets($field_settings['storage'][$subfield]['type'], $field_settings[$subfield]['list']);
      // Use the first eligible widget type unless it is set explicitly.
      if (empty($settings[$subfield]['type'])) {
        $settings[$subfield]['type'] = key($widget_types);
      }
      if ($field_settings['storage'][$subfield]['type'] == 'text_long') {
        $settings[$subfield]['type'] = 'textarea';
        $settings[$subfield]['editor'] = TRUE;
      }
    }

    return $settings;
  }

  /**
   * Determines whether widget can render subfield label.
   */
  public static function isLabelSupported(string $widget_type): bool {
    return $widget_type != 'checkbox';
  }

}
