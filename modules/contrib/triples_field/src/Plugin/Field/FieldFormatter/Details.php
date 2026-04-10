<?php

namespace Drupal\triples_field\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementations for 'details' formatter.
 *
 * @FieldFormatter(
 *   id = "triples_field_details",
 *   label = @Translation("Details"),
 *   field_types = {"triples_field"}
 * )
 */
class Details extends Base {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'open' => TRUE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $settings = $this->getSettings();

    $element['open'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Open'),
      '#default_value' => $settings['open'],
    ];

    $element += parent::settingsForm($form, $form_state);

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $open = $this->getSetting('open');
    $summary[] = $this->t('Open: @open', ['@open' => $open ? $this->t('yes') : $this->t('no')]);
    return array_merge($summary, parent::settingsSummary());
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $element = [];
    $field_settings = $this->getFieldSettings();
    $settings = $this->getSettings();

    $configs = $this->configFactory->get('triples_field.settings');
    $subfields = array_keys($titles = $configs->get('fields'));
    $attributes = [
      Html::getId('triples_field--field-name') => $items->getName(),
      'class' => ['double-field-details'],
    ];

    foreach ($items as $delta => $item) {

      $values = [];
      $labels = [];
      foreach ($subfields as $subfield) {
        $values[$subfield] = $item->{$subfield};
        $labels[$subfield] = $settings[$subfield]["show_label"] ? $field_settings[$subfield]["label"] : '';
      }
      $firstValues = array_shift($values);
      $firstKey = current($subfields);
      if (!empty($labels[$firstKey])) {
        $firstValues = [
          '#theme' => 'triples_field_subfield',
          '#subfield' => $firstValues,
          '#label' => $labels[$firstKey],
          '#field_name' => $firstKey,
          '#index' => $firstKey,
        ];
      }

      $element[$delta] = [
        '#title' => $firstValues,
        '#type' => 'details',
        '#open' => $settings['open'],
        '#attributes' => $attributes,
      ];
      foreach ($values as $subfield => $value) {
        if (!empty($labels[$subfield])) {
          $value = [
            '#theme' => 'triples_field_subfield',
            '#subfield' => $value,
            '#label' => $labels[$subfield],
            '#field_name' => $subfield,
            '#index' => $subfield,
          ];
          $value = $this->renderer->render($value);
        }
        $element[$delta]['#value'][$subfield] = [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $value,
          '#attributes' => [
            'class' => [$subfield],
          ],
        ];
      }
    }

    return $element;
  }

}
