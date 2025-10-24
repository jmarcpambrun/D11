<?php

namespace Drupal\webform_iban_field\Element;

use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\Textfield;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\Validator\Constraints\Iban;
use Symfony\Component\Validator\Validation;

/**
 * Provides a 'webform_iban_field'.
 *
 * @FormElement("webform_iban_field")
 */
class WebformIbanField extends Textfield {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#size' => 60,
      '#process' => [
        [$class, 'processWebformIbanField'],
        [$class, 'processAjaxForm'],
      ],
      '#element_validate' => [
        [$class, 'validateWebformIbanField'],
      ],
      '#pre_render' => [
        [$class, 'preRenderWebformIbanField'],
      ],
      '#theme' => 'input__webform_iban_field',
      '#theme_wrappers' => ['form_element'],
    ];
  }

  /**
   * Processes 'webform_iban_field' element.
   *
   * @param array $element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The complete form.
   *
   * @return array
   *   Returns the element.
   */
  public static function processWebformIbanField(array $element, FormStateInterface $form_state, array &$complete_form) {
    return $element;
  }

  /**
   * Webform element validation handler for #type 'webform_iban_field'.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The complete form.
   */
  public static function validateWebformIbanField(array &$element, FormStateInterface $form_state, array &$complete_form) {
    // Here you can add custom validation logic.
    $name = $element['#name'];
    $value = $form_state->getValue($name);
    $valid = TRUE;
    $multiple = $element['#webform_multiple'] ?? FALSE;

    if ($multiple && !$value) {
      $value = $element['#value'];
    }

    if ((string) $value === '0') {
      $valid = FALSE;
    }

    if ($value && $valid) {
      $validator = Validation::createValidator();
      $violations = $validator->validate($value, [
        new Iban(),
      ]);

      if (count($violations) > 0) {
        $valid = FALSE;
      }
    }

    if (!$valid) {
      $form_state->setError(
        $element,
        t('The value %value for element %name is not a valid IBAN.', [
          '%name' => $element['#title'] ?? $element['#parents'][0],
          '%value' => $value,
        ])
      );
    }
  }

  /**
   * Render element #type 'webform_iban_field_multiple' for theme_element().
   *
   * @param array $element
   *   An associative array containing the properties of the element.
   *   Properties used: #title, #value, #description, #size, #maxlength,
   *   #placeholder, #required, #attributes.
   *
   * @return array
   *   The $element with prepared variables ready for theme_element().
   */
  public static function preRenderWebformIbanField(array $element) {
    $element['#attributes']['type'] = 'text';
    Element::setAttributes($element, [
      'id',
      'name',
      'value',
      'size',
      'maxlength',
      'placeholder',
    ]);
    static::setAttributes($element, ['form-text', 'webform-iban-field']);
    return $element;
  }

}
