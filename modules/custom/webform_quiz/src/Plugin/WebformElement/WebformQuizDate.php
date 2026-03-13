<?php

namespace Drupal\webform_quiz\Plugin\WebformElement;

use Drupal\webform\Plugin\WebformElement\Date;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform_quiz\QuizWebformElementBase;
use Drupal\webform_quiz\QuizWebformElementBaseInterface;

/**
 * Provides a 'webform_quiz_date' element.
 *
 * @WebformElement(
 *   id = "webform_quiz_date",
 *   api = "https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Render!Element!Date.php/class/Date",
 *   label = @Translation("Webform Quiz Date"),
 *   description = @Translation("Provides a form element for date selection, with correct answers provided."),
 *   category = @Translation("Webform Quiz"),
 * )
 */
class WebformQuizDate extends Date implements QuizWebformElementBaseInterface {
  use QuizWebformElementBase;

  /**
   * {@inheritdoc}
   */
  public function getItemFormat(array $element) {
    $format = parent::getItemFormat($element);
    // Drupal's default date fallback includes the time so we need to fallback
    // to the specified or default date only format.
    if ($format === 'fallback') {
      $format = (isset($element['#date_date_format'])) ? $element['#date_date_format'] : $this->getDefaultProperty('date_date_format');
    }
    return $format;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['date']['date_container']['correct_answer'] = [
      '#type' => 'date',
      '#title' => $this->t('Correct Answer'),
      '#description' => $this->t('Correct Answer.'),
      '#min' => 1,
      '#size' => 4,
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
