<?php

namespace Drupal\field_group\Plugin\field_group\FieldGroupFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\field_group\FieldGroupFormatterBase;

/**
 * Plugin implementation of the 'fieldset' formatter.
 *
 * @FieldGroupFormatter(
 *   id = "fieldset",
 *   label = @Translation("Fieldset"),
 *   description = @Translation("This fieldgroup renders the inner content in a fieldset with the title as legend."),
 *   supported_contexts = {
 *     "form",
 *     "view",
 *   }
 * )
 */
class Fieldset extends FieldGroupFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function process(&$element, $processed_array) {

    $element += [
      '#type' => 'fieldset',
      '#title' => $this->getSetting('label_as_html') ? Markup::create(Xss::filterAdmin($this->getLabel())) : Markup::create(Html::escape($this->getLabel())),
      '#attributes' => [],
      '#description' => $this->getSetting('description'),
      '#description_display' => 'after',
      // Prevent \Drupal\content_translation\ContentTranslationHandler::addTranslatabilityClue()
      // from adding an incorrect suffix to the field group title.
      '#multilingual' => TRUE,
    ];

    // When a fieldset has a description, an id is required.
    if ($this->getSetting('description') && !$this->getSetting('id')) {
      $element['#id'] = Html::getUniqueId($this->group->group_name);
    }

    if ($this->getSetting('id')) {
      $element['#id'] = Html::getUniqueId($this->getSetting('id'));
    }

    $classes = $this->getClasses();
    if (!empty($classes)) {
      $element['#attributes'] += ['class' => $classes];
    }

    if ($this->getSetting('required_fields')) {
      $element['#attached']['library'][] = 'field_group/formatter.fieldset';
      $element['#attached']['library'][] = 'field_group/core';
    }

  }

  /**
   * {@inheritdoc}
   */
  public function preRender(&$element, $render_array) {
    parent::preRender($element, $render_array);
    $this->process($element, $render_array);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {

    $form = parent::settingsForm($form, $form_state);

    $form['description'] = [
      '#title' => $this->t('Description'),
      '#type' => 'textarea',
      '#default_value' => $this->getSetting('description'),
      '#weight' => -4,
    ];

    if ($this->context == 'form') {
      $form['required_fields'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Mark group as required if it contains required fields.'),
        '#default_value' => $this->getSetting('required_fields'),
        '#weight' => 2,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {

    $summary = parent::settingsSummary();

    if ($this->getSetting('required_fields')) {
      $summary[] = $this->t('Mark as required');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultContextSettings($context) {
    $defaults = [
      'description' => '',
    ] + parent::defaultContextSettings($context);

    if ($context == 'form') {
      $defaults['required_fields'] = 1;
    }

    return $defaults;
  }

}
