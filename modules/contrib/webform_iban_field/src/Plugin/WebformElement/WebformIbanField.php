<?php

namespace Drupal\webform_iban_field\Plugin\WebformElement;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformElement\TextBase;

/**
 * Provides a 'webform_iban_field' element.
 *
 * @WebformElement(
 *   id = "webform_iban_field",
 *   label = @Translation("Webform IBAN field"),
 *   description = @Translation("Provides a webform IBAN field."),
 *   category = @Translation("Advanced elements"),
 * )
 */
class WebformIbanField extends TextBase {

  /**
   * {@inheritdoc}
   */
  public function getDefaultProperties() {
    return parent::getDefaultProperties() + [
      'multiple' => '',
      'size' => '',
      'minlength' => '',
      'maxlength' => '',
      'placeholder' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    return $form;
  }

}
