<?php

namespace Drupal\webform_quiz\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Element\WebformElementOptions;
use Drupal\webform_quiz\QuizWebformElementBaseInterface;

/**
 * Provides a form element for managing webform element options.
 *
 * This element is used by select, radios, checkboxes, likert, and
 * mapping elements.
 *
 * @FormElement("webform_quiz_webform_element_options")
 */
class WebformQuizWebformElementOptions extends WebformElementOptions {

  /**
   * Processes a webform element options element.
   */
  public static function processWebformElementOptions(&$element, FormStateInterface $form_state, &$complete_form) {
    parent::processWebformElementOptions($element, $form_state, $complete_form);
    $storage = $form_state->getStorage();
    $element_properties = $storage['element_properties'];

    $element['custom']['#type'] = 'webform_quiz_webform_options';

    $element['webform_quiz_element_mode'] = [
      '#type' => 'select',
      '#options' => [
        QuizWebformElementBaseInterface::WEBFORM_ELEMENT_MODE_QUIZ => t('Quiz'),
        QuizWebformElementBaseInterface::WEBFORM_ELEMENT_MODE_SURVEY => t('Survey'),
      ],
      '#required' => 'true',
      '#default_value' => @$element_properties['webform_quiz_element_mode'],
    ];

    return $element;
  }

}
