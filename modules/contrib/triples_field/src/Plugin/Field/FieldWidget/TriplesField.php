<?php

namespace Drupal\triples_field\Plugin\Field\FieldWidget;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
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
 *   id = "triples_field",
 *   label = @Translation("Triple Field"),
 *   field_types = {"triples_field"}
 * )
 */
class TriplesField extends WidgetBase {

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
  public static function defaultSettings(): array {
    $configs = \Drupal::config('triples_field.settings');
    $subfields = array_keys($configs->get('fields'));
    foreach ($subfields as $subfield) {
      $settings[$subfield] = [
        // As this method is static there is no way to set an appropriate type
        // for the subwidget. Let self::getSettings() do it instead.
        'type' => NULL,
        'label_display' => 'block',
        'size' => 30,
        'placeholder' => '',
        'label' => t('Ok'),
        'cols' => 10,
        'rows' => 5,
      ];
    }
    $settings['inline'] = FALSE;

    return $settings + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {

    $settings = $this->getSettings();
    $field_settings = $this->getFieldSettings();

    $types = FieldItem::subfieldTypes();

    $field_name = $this->fieldDefinition->getName();

    $element['inline'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display as inline element'),
      '#default_value' => $settings['inline'],
    ];

    $configs = $this->configFactory->get('triples_field.settings');
    $subfields = array_keys($titles = $configs->get('fields'));
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
        '#required' => TRUE,
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
        '#required' => TRUE,
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
    if ($settings['inline']) {
      $summary[] = $this->t('Display as inline element');
    }

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
      if (static::isLabelSupported($settings[$subfield]['type'])) {
        $summary[] = $this->t('Label display: @label', ['@label' => $settings[$subfield]['label_display'] ?? '']);
      }
      switch ($settings[$subfield]['type']) {
        case 'textfield':
        case 'email':
        case 'tel':
        case 'url':
          $summary[] = $this->t('Size: @size', ['@size' => $settings[$subfield]['size'] ?? '']);
          if (!empty($settings[$subfield]['placeholder'])) {
            $summary[] = $this->t('Placeholder: @placeholder', ['@placeholder' => $settings[$subfield]['placeholder']]);
          }
          break;

        case 'checkbox':
          $summary[] = $this->t('Label: @label', ['@label' => $settings[$subfield]['label'] ?? '']);
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
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {

    $field_settings = $this->getFieldSettings();
    $settings = $this->getSettings();
    $subfields = array_keys($field_settings["storage"]);
    $widget = [
      '#theme_wrappers' => ['container', 'form_element'],
      '#attributes' => ['class' => [Html::getId('triples_field-elements')]],
      '#attached' => [
        'library' => ['triples_field/widget'],
      ],
    ];

    if ($settings['inline']) {
      $widget['#attributes']['class'][] = Html::getId('triples_field-widget-inline');
    }

    foreach ($subfields as $subfield) {

      $widget[$subfield] = [
        '#type' => $settings[$subfield]['type'],
        '#default_value' => $items[$delta]->{$subfield} ?? NULL,
        '#subfield_settings' => $settings[$subfield],
        '#wrapper_attributes' => ['class' => [Html::getId('triples_field-subfield-form-item')]],
      ];

      $label_display = $settings[$subfield]['label_display'] ?? 'hidden';
      $label = $label_display == 'hidden' ? '' : $field_settings[$subfield]['label'];
      $widget_type = $settings[$subfield]['type'];
      if ($label_display != 'hidden' && self::isLabelSupported($widget_type)) {
        $widget[$subfield]['#title'] = $label;
        if ($label_display == 'invisible') {
          $widget[$subfield]['#title_display'] = 'invisible';
        }
        elseif ($label_display == 'inline') {
          $widget[$subfield]['#wrapper_attributes']['class'][] = 'container-inline';
        }
      }

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
          if (isset($max_length_map[$storage_type])) {
            $widget[$subfield]['#maxlength'] = $max_length_map[$storage_type];
          }
          if ($settings[$subfield]['size']) {
            $widget[$subfield]['#size'] = $settings[$subfield]['size'];
          }
          if ($settings[$subfield]['placeholder']) {
            $widget[$subfield]['#placeholder'] = $settings[$subfield]['placeholder'];
          }
          break;

        case 'checkbox':
          $widget[$subfield]['#title'] = $settings[$subfield]['label'];
          break;

        case 'select':
          $label = $field_settings[$subfield]['required'] ? $this->t('- Select a value -') : $this->t('- None -');
          $widget[$subfield]['#options'] = ['' => $label];
          if ($field_settings[$subfield]['list']) {
            $widget[$subfield]['#options'] += $field_settings[$subfield]['allowed_values'];
          }
          break;

        case 'radios':
          $label = $field_settings[$subfield]['required'] ? $this->t('N/A') : $this->t('- None -');
          $widget[$subfield]['#options'] = ['' => $label];
          if ($field_settings[$subfield]['list']) {
            $widget[$subfield]['#options'] += $field_settings[$subfield]['allowed_values'];
          }
          break;

        case 'textarea':
          if ($settings[$subfield]['cols']) {
            $widget[$subfield]['#cols'] = $settings[$subfield]['cols'];
          }
          if ($settings[$subfield]['rows']) {
            $widget[$subfield]['#rows'] = $settings[$subfield]['rows'];
          }
          if ($settings[$subfield]['placeholder']) {
            $widget[$subfield]['#placeholder'] = $settings[$subfield]['placeholder'];
          }
          if (!empty($settings[$subfield]['editor'])) {
            $value_element = $widget[$subfield];
            $widget[$subfield] = [];
            $widget[$subfield]['#default_value'] = $value_element['#default_value'];
            $widget[$subfield]['value'] = $value_element;
            $widget[$subfield]['#type'] = 'text_format';
            $widget[$subfield]['#format'] = $items[$delta]->{$subfield . '_format'} ?? NULL;
            $widget[$subfield]['#base_type'] = 'textarea';
          }
          break;

        case 'number':
        case 'range':
          if (in_array($storage_type, ['integer', 'float', 'numeric'])) {
            if ($field_settings[$subfield]['min']) {
              $widget[$subfield]['#min'] = $field_settings[$subfield]['min'];
            }
            if ($field_settings[$subfield]['max']) {
              $widget[$subfield]['#max'] = $field_settings[$subfield]['max'];
            }
            if ($storage_type == 'numeric') {
              $widget[$subfield]['#step'] = pow(0.1, $field_settings['storage'][$subfield]['scale']);
            }
            elseif ($storage_type == 'float') {
              $widget[$subfield]['#step'] = 'any';
            }
          }
          break;

        case 'datetime':
          $widget[$subfield]['#default_value'] = $items[$delta]->createDate($subfield);
          if ($field_settings['storage'][$subfield]['datetime_type'] == 'date') {
            $widget[$subfield]['#date_time_element'] = 'none';
            $widget[$subfield]['#date_time_format'] = '';
          }
          else {
            if ($widget[$subfield]['#default_value']) {
              $widget[$subfield]['#default_value']->setTimezone(new \DateTimezone(date_default_timezone_get()));
            }
            // Ensure that the datetime field processing doesn't set its own
            // time zone here.
            $widget[$subfield]['#date_timezone'] = date_default_timezone_get();
          }

          break;

      }

    }

    return $element + $widget;
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
    $settings = parent::getSettings();
    $field_settings = $this->getFieldSettings();
    $subfields = array_keys($field_settings["storage"]);
    foreach ($subfields as $subfield) {
      $widget_types = $this->getSubwidgets($field_settings['storage'][$subfield]['type'], $field_settings[$subfield]['list']);
      // Use the first eligible widget type unless it is set explicitly.
      if (!$settings[$subfield]['type']) {
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
   * Determines whether or not widget can render subfield label.
   */
  public static function isLabelSupported(string $widget_type): bool {
    return $widget_type != 'checkbox';
  }

}
