<?php

namespace Drupal\triples_field\Plugin\Field\FieldFormatter;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\triples_field\Plugin\Field\FieldType\TriplesField as TriplesFieldItem;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for triples field formatters.
 */
abstract class Base extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, protected ConfigFactoryInterface $configFactory, protected RendererInterface $renderer, protected EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('config.factory'),
      $container->get('renderer'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Subfield types that can be rendered as a link.
   *
   * @var array
   */
  protected static $linkTypes = ['email', 'telephone', 'uri'];

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    $configs = \Drupal::config('triples_field.settings');
    $subfields = array_keys($configs->get('fields'));
    $settings = [];
    foreach ($subfields as $subfield) {
      $settings[$subfield] = [
        // Hidden option is useful to display data with Views module.
        'hidden' => FALSE,
        'show_label' => FALSE,
        'link' => FALSE,
        'format_type' => 'medium',
        // @todo Create tests for this options.
        'thousand_separator' => '',
        'decimal_separator' => '.',
        'scale' => 2,
        'key' => FALSE,
      ];
    }
    return $settings + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $settings = $this->getSettings();
    $field_settings = $this->getFieldSettings();
    $types = TriplesFieldItem::subfieldTypes();
    $element = [];

    $configs = $this->configFactory->get('triples_field.settings');
    $subfields = array_keys($titles = $configs->get('fields'));
    // General settings.
    foreach ($subfields as $subfield) {
      $type = $field_settings['storage'][$subfield]['type'];

      $title = $titles[$subfield];
      $title .= ' - ' . $types[$type];
      if ($field_settings[$subfield]['list']) {
        $title .= ' (' . $this->t('list') . ')';
      }

      $element[$subfield] = [
        '#title' => $title,
        '#type' => 'details',
      ];

      $element[$subfield]['link'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Display as link'),
        '#default_value' => $settings[$subfield]['link'],
        '#weight' => -10,
        '#access' => in_array($type, static::$linkTypes),
      ];

      if ($type == 'datetime_iso8601') {
        $format_types = $this->entityTypeManager->getStorage('date_format')->loadMultiple();
        $date_formatter = static::getDateFormatter();
        $time = new DrupalDateTime();
        $options = [];
        foreach ($format_types as $type => $type_info) {
          $format = $date_formatter->format($time->getTimestamp(), $type);
          $options[$type] = $type_info->label() . ' (' . $format . ')';
        }
        $element[$subfield]['format_type'] = [
          '#type' => 'select',
          '#title' => $this->t('Date format'),
          '#description' => $this->t('Choose a format for displaying the date.'),
          '#options' => $options,
          '#default_value' => $settings[$subfield]['format_type'],
        ];
      }
      else {
        $element[$subfield]['format_type'] = [
          '#type' => 'value',
          '#default_value' => $settings[$subfield]['format_type'],
        ];
      }

      $element[$subfield]['hidden'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Hidden'),
        '#default_value' => $settings[$subfield]['hidden'],
      ];

      $element[$subfield]['show_label'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Show label'),
        '#default_value' => $settings[$subfield]['show_label'],
      ];

      if ($field_settings[$subfield]['list']) {
        $element[$subfield]['key'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Display key instead of label'),
          // @todo Remove the fallback on 5.x.
          '#default_value' => $settings[$subfield]['key'] ?? FALSE,
        ];
      }

      if ($type == 'numeric' || $type == 'float' || $type == 'integer') {
        $options = [
          '' => $this->t('- None -'),
          '.' => $this->t('Decimal point'),
          ',' => $this->t('Comma'),
          ' ' => $this->t('Space'),
          chr(8201) => $this->t('Thin space'),
          "'" => $this->t('Apostrophe'),
        ];
        $element[$subfield]['thousand_separator'] = [
          '#type' => 'select',
          '#title' => $this->t('Thousand marker'),
          '#options' => $options,
          '#default_value' => $settings[$subfield]['thousand_separator'],
        ];
      }
      else {
        $element[$subfield]['thousand_separator'] = [
          '#type' => 'value',
          '#default_value' => $settings[$subfield]['thousand_separator'],
        ];
      }

      if ($type == 'numeric' || $type == 'float') {
        $element[$subfield]['decimal_separator'] = [
          '#type' => 'select',
          '#title' => $this->t('Decimal marker'),
          '#options' => [
            '.' => $this->t('Decimal point'),
            ',' => $this->t('Comma'),
          ],
          '#default_value' => $settings[$subfield]['decimal_separator'],
        ];
        $element[$subfield]['scale'] = [
          '#type' => 'number',
          '#title' => $this->t('Scale', [], ['context' => 'decimal places']),
          '#min' => 0,
          '#max' => 10,
          '#default_value' => $settings[$subfield]['scale'],
          '#description' => $this->t('The number of digits to the right of the decimal.'),
        ];
      }
      else {
        $element[$subfield]['decimal_separator'] = [
          '#type' => 'value',
          '#default_value' => $settings[$subfield]['decimal_separator'],
        ];
        $element[$subfield]['scale'] = [
          '#type' => 'value',
          '#default_value' => $settings[$subfield]['scale'],
        ];
      }

    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $settings = $this->getSettings();
    $field_settings = $this->getFieldSettings();

    $subfield_types = TriplesFieldItem::subfieldTypes();

    $summary = [];
    $configs = $this->configFactory->get('triples_field.settings');
    $subfields = array_keys($titles = $configs->get('fields'));
    foreach ($subfields as $subfield) {
      $subfield_type = $field_settings['storage'][$subfield]['type'];
      $summary[] = new FormattableMarkup(
        '<b>@subfield - @subfield_type@list</b>',
        [
          '@subfield' => $titles[$subfield],
          '@subfield_type' => strtolower($subfield_types[$subfield_type]),
          '@list' => $field_settings[$subfield]['list'] ? ' (' . $this->t('list') . ')' : '',
        ]
      );
      if ($subfield_type == 'datetime_iso8601') {
        $summary[] = $this->t('Date format: @format', ['@format' => $settings[$subfield]['format_type']]);
      }
      if (in_array($subfield_type, static::$linkTypes)) {
        $summary[] = $this->t('Link: @value', ['@value' => $settings[$subfield]['link'] ? $this->t('yes') : $this->t('no')]);
      }
      $summary[] = $this->t('Hidden: @value', ['@value' => $settings[$subfield]['hidden'] ? $this->t('yes') : $this->t('no')]);
      if ($field_settings[$subfield]['list']) {
        // @todo Remove the fallback in 5.x.
        $display_key = $settings[$subfield]['key'] ?? FALSE;
        $summary[] = $this->t('Display key: @value', ['@value' => $display_key ? $this->t('yes') : $this->t('no')]);

      }
      if ($subfield_type == 'numeric' || $subfield_type == 'float' || $subfield_type == 'integer') {
        $summary[] = $this->t('Number format: @format', ['@format' => $this->numberFormat($subfield, 1234.1234567890)]);
      }
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function view(FieldItemListInterface $items, $langcode = NULL): array {
    $elements = [];
    if (count($items) > 0) {
      // A field may appear multiple times in a single view. Since items are
      // passed by reference we need to ensure they are processed only once.
      $items = clone $items;
      $this->prepareItems($items);
      $elements = parent::view($items, $langcode);
    }
    return $elements;
  }

  /**
   * Prepare field items.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   List of field items.
   */
  protected function prepareItems(FieldItemListInterface $items): void {

    $field_settings = $this->getFieldSettings();
    $settings = $this->getSettings();

    $subfields = array_keys($field_settings['storage']);
    foreach ($items as $delta => $item) {
      foreach ($subfields as $subfield) {

        if ($settings[$subfield]['hidden']) {
          $item->{$subfield} = NULL;
        }
        else {

          $type = $field_settings['storage'][$subfield]['type'];

          if ($type == 'boolean') {
            $item->{$subfield} = $field_settings[$subfield][$item->{$subfield} ? 'on_label' : 'off_label'];
          }

          if ($field_settings['storage'][$subfield]['type'] == 'text_long') {
            $item->{$subfield} = [
              '#type' => 'processed_text',
              '#text' => $item->{$subfield},
              '#format' => $item->{$subfield . '_format'},
            ];
          }

          // Empty string should already be converted into NULL.
          // @see Drupal\triples_field\Plugin\Field\FieldWidget\TriplesField::massageFormValues()
          if ($item->{$subfield} === NULL) {
            continue;
          }

          if ($type == 'numeric' || $type == 'float' || $type == 'integer') {
            $item->{$subfield} = $this->numberFormat($subfield, $item->{$subfield});
          }

          if ($type == 'datetime_iso8601' && $item->{$subfield} && !$field_settings[$subfield]['list']) {
            // We follow the same principles as Drupal Core.
            // In the case of a datetime subfield, the date must be parsed using
            // the storage time zone and converted to the user's time zone while
            // a date-only field should have no timezone conversion performed.
            $timezone = $field_settings['storage'][$subfield]['datetime_type'] === 'datetime' ?
              date_default_timezone_get() : TriplesFieldItem::DATETIME_STORAGE_TIMEZONE;
            $timestamp = $items[$delta]->createDate($subfield)->getTimestamp();
            $date_formatter = static::getDateFormatter();
            $item->{$subfield} = [
              '#theme' => 'time',
              '#text' => $date_formatter->format($timestamp, $settings[$subfield]['format_type'], '', $timezone),
              '#html' => FALSE,
              '#attributes' => [
                'datetime' => $date_formatter->format($timestamp, 'custom', 'Y-m-d\TH:i:s') . 'Z',
              ],
              '#cache' => [
                'contexts' => [
                  'timezone',
                ],
              ],
            ];
          }

          $original_value[$subfield] = $item->{$subfield};
          if ($field_settings[$subfield]['list']) {
            // @todo Remove the fallback in 5.x.
            $display_key = $settings[$subfield]['key'] ?? FALSE;
            if (!$display_key) {
              // Replace the value with its label if possible.
              $item->{$subfield} = $field_settings[$subfield]['allowed_values'][$item->{$subfield}] ?? NULL;
            }
          }

          if (!empty($settings[$subfield]['link'])) {
            $value = !empty($original_value[$subfield]) ? $original_value[$subfield] : $item->{$subfield};
            switch ($type) {
              case 'email':
                $item->{$subfield} = [
                  '#type' => 'link',
                  '#title' => $item->{$subfield},
                  '#url' => Url::fromUri('mailto:' . $value),
                ];
                break;

              case 'telephone':
                $item->{$subfield} = [
                  '#type' => 'link',
                  '#title' => $item->{$subfield},
                  '#url' => Url::fromUri('tel:' . rawurlencode(preg_replace('/\s+/', '', $value))),
                  '#options' => ['external' => TRUE],
                ];
                break;

              case 'uri':
                $item->{$subfield} = [
                  '#type' => 'link',
                  '#title' => $item->{$subfield},
                  '#url' => Url::fromUri($value),
                  '#options' => ['external' => TRUE],
                ];
                break;

            }
          }

        }

      }
      $items[$delta] = $item;
    }
  }

  /**
   * Formats a number.
   */
  protected function numberFormat(string $subfield, string $number): string {
    $settings = $this->getSetting($subfield);
    if ($this->getFieldSetting('storage')[$subfield]['type'] == 'integer') {
      $settings['scale'] = 0;
    }
    return number_format($number, $settings['scale'], $settings['decimal_separator'], $settings['thousand_separator']);
  }

  /**
   * Returns date formatter.
   */
  protected static function getDateFormatter(): DateFormatterInterface {
    return \Drupal::service('date.formatter');
  }

}
