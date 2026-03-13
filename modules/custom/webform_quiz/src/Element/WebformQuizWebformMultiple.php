<?php

namespace Drupal\webform_quiz\Element;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Element\WebformMultiple;
use Drupal\webform_quiz\QuizWebformElementBaseInterface;

/**
 * Provides a webform element to assist in creation of multiple elements.
 *
 * @FormElement("webform_quiz_webform_multiple")
 */
class WebformQuizWebformMultiple extends WebformMultiple {

  /**
   * {@inheritdoc}
   */
  public static function initializeElement(array &$element, FormStateInterface $form_state, array &$complete_form) {
    parent::initializeElement($element, $form_state, $complete_form);

    $form_state_storage = $form_state->getStorage();
    $key = $form_state_storage['machine_name.initial_values']['key'];
    $element['#parent_webform_element_key'] = $key;
  }

  /**
   * Helper function to add state behaviour to talble cells and header cells
   *
   * @param array $el
   * @param string $mode
   * @return void
   */
  protected static function visibleForMode(array &$el, string $mode) {
    $el['class'][] = 'js-form-item form-item';
    $el['data-drupal-states'] = '{"visible":{":input[name=\u0022properties[options][webform_quiz_element_mode]\u0022]":{"value":"'.$mode.'"}}}';
  }

  /**
   * {@inheritdoc}
   */
  protected static function buildElementRow($table_id, $row_index, array $element, $default_value, $weight, array $ajax_settings) {
    // Check the correct answers in the configuration form.
    $row = parent::buildElementRow($table_id, $row_index, $element, $default_value, $weight, $ajax_settings);

    /** @var \Drupal\webform\Plugin\WebformSourceEntityManager $element_manager */
    $entity_manager = \Drupal::service('plugin.manager.webform.source_entity');

    /** @var \Drupal\webform\Entity\Webform $webform */
    $webform = $entity_manager->getSourceEntity();
    $element_config = $webform->getElement($element['#parent_webform_element_key']);

    if (isset($row['option_value']["value"]["#default_value"]) && isset($element_config['#correct_answer'])) {
      $row['is_correct_answer']['#default_value'] = in_array($row['option_value']["value"]["#default_value"], $element_config['#correct_answer']);
    }

    $row['is_correct_answer']['#wrapper_attributes'] = @$row['is_correct_answer']['#wrapper_attributes'] ?? [];
    $row['answer_score']['#wrapper_attributes'] = @$row['answer_score']['#wrapper_attributes'] ?? [];

    static::visibleForMode($row['is_correct_answer']['#wrapper_attributes'], QuizWebformElementBaseInterface::WEBFORM_ELEMENT_MODE_QUIZ);
    static::visibleForMode($row['answer_score']['#wrapper_attributes'], QuizWebformElementBaseInterface::WEBFORM_ELEMENT_MODE_SURVEY);
    /*
    $row['is_correct_answer']['#wrapper_attributes']['class'][] = 'js-form-item form-item';
    $row['is_correct_answer']['#wrapper_attributes']['data-drupal-states'] = 
      '{"visible":{":input[name=\u0022properties[options][webform_quiz_element_mode]\u0022]":{"value":"'.QuizWebformElementBaseInterface::WEBFORM_ELEMENT_MODE_QUIZ.'"}}}';

    $row['answer_score']['#wrapper_attributes']['class'][] = 'js-form-item form-item';
    $row['answer_score']['#wrapper_attributes']['data-drupal-states'] = 
      '{"visible":{":input[name=\u0022properties[options][webform_quiz_element_mode]\u0022]":{"value":"'.QuizWebformElementBaseInterface::WEBFORM_ELEMENT_MODE_SURVEY.'"}}}';
    */

    return $row;
  }


  protected static function buildElementHeader(array $element) {
    $header = parent::buildElementHeader($element);
    if(isset($header['is_correct_answer'])) {
      static::visibleForMode($header['is_correct_answer'], QuizWebformElementBaseInterface::WEBFORM_ELEMENT_MODE_QUIZ);
      /*
      $header['is_correct_answer']['class'][] = 'js-form-item form-item';
      $header['is_correct_answer']['data-drupal-states'] = 
        '{"visible":{":input[name=\u0022properties[options][webform_quiz_element_mode]\u0022]":{"value":"'.QuizWebformElementBaseInterface::WEBFORM_ELEMENT_MODE_QUIZ.'"}}}';
      */
    }
    if(isset($header['answer_score'])) {
      static::visibleForMode($header['answer_score'], QuizWebformElementBaseInterface::WEBFORM_ELEMENT_MODE_SURVEY);
      /*
      $header['answer_score']['class'][] = 'js-form-item form-item';
      $header['answer_score']['data-drupal-states'] = 
        '{"visible":{":input[name=\u0022properties[options][webform_quiz_element_mode]\u0022]":{"value":"'.QuizWebformElementBaseInterface::WEBFORM_ELEMENT_MODE_SURVEY.'"}}}';
      */
    }

    return $header;
  }

  /**
   * Validates webform multiple element.
   */
  public static function validateWebformMultiple(&$element, FormStateInterface $form_state, &$complete_form) {
    // IMPORTANT: Must get values from the $form_states since sub-elements
    // may call $form_state->setValueForElement() via their validation hook.
    // @see \Drupal\webform\Element\WebformEmailConfirm::validateWebformEmailConfirm
    // @see \Drupal\webform\Element\WebformOtherBase::validateWebformOther
    $values = NestedArray::getValue($form_state->getValues(), $element['#parents']);
    foreach(array_keys($values['items']) as $key) {
      if(empty($values['items'][$key]['text']) && empty($values['items'][$key]['description'])) {
        unset($values['items'][$key]);
      }
    }

    parent::validateWebformMultiple($element, $form_state, $complete_form);

  }

}
