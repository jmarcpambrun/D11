<?php

namespace Drupal\webform_quiz\Plugin\WebformElement;

use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\Plugin\WebformElement\TextField;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform_quiz\QuizWebformElementBase;
use Drupal\webform_quiz\QuizWebformElementBaseInterface;

/**
 * Provides a 'webform_quiz_textfield' element.
 *
 * @WebformElement(
 *   id = "webform_quiz_textfield",
 *   api = "https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Render!Element!Textfield.php/class/Textfield",
 *   label = @Translation("Webform Quiz Text field"),
 *   description = @Translation("Provides a form element for input of a single-line text with correct answer."),
 *   category = @Translation("Webform Quiz"),
 * )
 */
class WebformQuizTextField extends TextField implements QuizWebformElementBaseInterface {
  use QuizWebformElementBase;

  /**
   * {@inheritdoc}
   */
  public function isStrictCompare(array $element, ?array $caws = null) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function prepareValueForCompare($value = NULL) {
    if (is_array($value)) {
      foreach ($value as $key => $val) {
        $value[$key] = static::prepareValueForCompare($val);
      }
    }
    else {
      $value = explode(';;', $value);
      if (count($value) > 1) {
        $value = static::prepareValueForCompare($value);
      }
      else {
        $value = reset($value);

        $value = str_replace(' ', "", $value);
        $value = str_replace("\t", "", $value);
        $value = str_replace('-', "", $value);
        $value = str_replace(',', "", $value);
        $value = str_replace('.', "", $value);
        $value = str_replace('/', "", $value);
        $value = str_replace("\\", "", $value);
        $value = mb_strtolower($value);
      }
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['validation']['correct_answer'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Correct Answer'),
      '#description' => $this->t('Correct Answer.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return $this->traitBuildConfigurationForm($form, $form_state);
  }

}
