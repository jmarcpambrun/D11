<?php

namespace Drupal\webform_quiz\Plugin\WebformElement;

use Drupal\webform\Plugin\WebformElement\Select;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform_quiz\QuizWebformElementBase;
use Drupal\webform_quiz\QuizWebformElementBaseInterface;

/**
 * Provides a 'webform_quiz_select' element.
 *
 * @WebformElement(
 *   id = "webform_quiz_select",
 *   api = "https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Render!Element!Select.php/class/Select",
 *   label = @Translation("Webform Quiz Select"),
 *   description = @Translation("Provides a form element for a drop-down menu or scrolling selection box, with a correct answer provided."),
 *   category = @Translation("Webform Quiz"),
 * )
 */
class WebformQuizSelect extends Select implements QuizWebformElementBaseInterface {
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
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return $this->traitBuildConfigurationForm($form, $form_state);
  }

}
