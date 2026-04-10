<?php

namespace Drupal\webform_quiz\Plugin\WebformElement;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformElement\Radios;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform_quiz\QuizWebformElementBase;
use Drupal\webform_quiz\QuizWebformElementBaseInterface;

/**
 * Provides a 'webform_quiz_radios' element.
 *
 * @WebformElement(
 *   id = "webform_quiz_radios",
 *   api = "https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Render!Element!Radios.php/class/Radios",
 *   label = @Translation("Webform Quiz Radios"),
 *   description = @Translation("Provides a form element for a set of radio buttons with a correct answer provided."),
 *   category = @Translation("Webform Quiz"),
 * )
 */
class WebformQuizRadios extends Radios implements QuizWebformElementBaseInterface {
  use QuizWebformElementBase;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['options']['options']['#type'] = 'webform_quiz_webform_element_options';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    // Make sure no blank options get submitted. If they are, just remove them.
    $values = $form_state->getValues();
    foreach ($values['options'] as $value) {
      if (empty($value)) {
        unset($values['options'][$value]);
      }
    }
    $form_state->setValues($values);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return $this->traitBuildConfigurationForm($form, $form_state);
  }

}
