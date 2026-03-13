<?php

namespace Drupal\triples_field\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementations for 'table' formatter.
 *
 * @FieldFormatter(
 *   id = "triples_field_table",
 *   label = @Translation("Table"),
 *   field_types = {"triples_field"}
 * )
 */
class Table extends Base {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    $default = [
      'number_column' => FALSE,
      'number_column_label' => '№',
    ];
    $configs = \Drupal::config('triples_field.settings');
    $subfields = array_keys($titles = $configs->get('fields'));
    foreach ($subfields as $subfield) {
      $default[$subfield . '_column_label'] = '';
    }
    return $default + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $settings = $this->getSettings();
    $element['number_column'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable row number column'),
      '#default_value' => $settings['number_column'],
      '#attributes' => ['class' => [Html::getId('js-triples_field-number-column')]],
    ];
    $element['number_column_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Number column label'),
      '#size' => 30,
      '#default_value' => $settings['number_column_label'],
      '#states' => [
        'visible' => ['.js-triples-field-number-column' => ['checked' => TRUE]],
      ],
    ];

    $configs = $this->configFactory->get('triples_field.settings');
    $subfields = array_keys($titles = $configs->get('fields'));
    foreach ($subfields as $subfield) {
      $element[$subfield . '_column_label'] = [
        '#type' => 'textfield',
        '#title' => $titles[$subfield],
        '#size' => 30,
        '#default_value' => $settings[$subfield . '_column_label'] ?: $this->getFieldSetting($subfield)['label'],
      ];
    }

    return $element + parent::settingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $settings = $this->getSettings();

    $summary[] = $this->t('Enable row number column: @number_column', ['@number_column' => $settings['number_column'] ? $this->t('yes') : $this->t('no')]);
    if ($settings['number_column']) {
      $summary[] = $this->t('Number column label: @number_column_label', ['@number_column_label' => $settings['number_column_label']]);
    }

    $configs = $this->configFactory->get('triples_field.settings');
    $subfields = array_keys($titles = $configs->get('fields'));
    foreach ($subfields as $subfield) {
      if ($settings[$subfield . '_column_label'] != '') {
        $summary[] = ucfirst($subfield) . ' ' .
          $this->t('column label: @column_label',
            ['@column_label' => $settings[$subfield . '_column_label']]
          );
      }
    }

    return array_merge($summary, parent::settingsSummary());
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $settings = $this->getSettings();

    $table = ['#type' => 'table'];

    // No other way to pass context to the theme.
    // @see triples_field_theme_suggestions_table_alter()
    $id = HTML::getId('triples_field');
    $table['#attributes'][$id . '--field-name'] = $items->getName();
    $table['#attributes']['class'][] = $id . '-table';

    $configs = $this->configFactory->get('triples_field.settings');
    $subfields = array_keys($titles = $configs->get('fields'));
    $header = [];
    if (!empty($settings['number_column'])) {
      $header = [$settings['number_column_label']];
    }
    foreach ($subfields as $subfield) {
      if (empty($settings['first_column_label'])) {
        $header = [];
        break;
      }
      $header[] = $settings[$subfield . '_column_label'];
    }
    if (!empty($header)) {
      $table['#header'] = $header;
    }

    $field_name = $items->getName();
    foreach ($items as $delta => $item) {
      $row = [];
      if ($settings['number_column']) {
        $row[]['#markup'] = $delta + 1;
      }

      foreach ($subfields as $subfield) {

        if ($settings[$subfield]['hidden']) {
          $row[]['#markup'] = '';
        }
        else {
          $label = '';
          if (!empty($settings[$subfield]) && !empty($settings[$subfield]["show_label"])) {
            $label = $settings[$subfield . '_column_label'];
          }
          $row[] = [
            '#theme' => 'triples_field_subfield',
            '#settings' => $settings,
            '#subfield' => $item->{$subfield},
            '#index' => $subfield,
            '#field_name' => $field_name,
            '#label' => $label,
          ];
        }
      }

      $table[$delta] = $row;
    }

    $element[0] = $table;

    return $element;
  }

}
