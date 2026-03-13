<?php

namespace Drupal\webform_quiz_image_select\Plugin\WebformElement;

use Drupal\webform_image_select\Plugin\WebformElement\WebformImageSelect;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform_quiz\QuizWebformElementBase;
use Drupal\webform_quiz\QuizWebformElementBaseInterface;

/**
 * Provides a 'webform_quiz_image_select' element.
 *
 * @WebformElement(
 *   id = "webform_quiz_image_select",
 *   label = @Translation("Webform Quiz Image select"),
 *   description = @Translation("Provides a form element for selecting images, with a correct answer provided."),
 *   category = @Translation("Webform Quiz"),
 * )
 */
class WebformQuizImageSelect extends WebformImageSelect implements QuizWebformElementBaseInterface {
  use QuizWebformElementBase;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {

    $form = parent::form($form, $form_state);
    $form['options']['images']['#type'] = 'webform_quiz_image_select_element_images';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return $this->traitBuildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function initialize(array &$element) {
    parent::initialize($element);
    $correct = [];
    foreach ($element['#images'] as $value => &$image) {
      if (!empty($image['is_correct_answer'])) {
        $correct[$value] = $value;
      }
      unset($image);
    }
    if (!empty($correct)) {
      $element['#correct_answer'] = $correct;
    }
  }

}
