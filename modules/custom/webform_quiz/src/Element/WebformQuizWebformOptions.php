<?php

namespace Drupal\webform_quiz\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Element\WebformOptions;
use Drupal\webform_quiz\QuizWebformElementBaseInterface;

/**
 * Provides a webform element to assist in creation of options.
 *
 * This provides a nicer interface for non-technical users to add values and
 * labels for options, possible within option groups.
 *
 * @FormElement("webform_quiz_webform_options")
 */
class WebformQuizWebformOptions extends WebformOptions {

  /**
   * {@inheritdoc}
   */
  public static function processWebformOptions(&$element, FormStateInterface $form_state, &$complete_form) {
    parent::processWebformOptions($element, $form_state, $complete_form);

    if ($element['options']['#type'] === 'webform_multiple') {
      $element['options']['#type'] = 'webform_quiz_webform_multiple';
    }

    $element['options']['#element']['is_correct_answer'] = [
      '#type' => 'checkbox',
      '#title' => t('Is this the correct answer?'),
      '#help' => t('Required for Quiz mode. You can select multiple correct answers.'),
      '#states' => [
        'visible' => [
          ':input[name="properties[options][webform_quiz_element_mode]"]' => ['value' => QuizWebformElementBaseInterface::WEBFORM_ELEMENT_MODE_QUIZ],
        ],
      ],
    ];

    $element['options']['#element']['answer_score'] = [
      '#type' => 'number',
      '#title' => t('Answer score'),
      '#help' => t('Number of points for this answer chosen in Survey mode.'),
      '#states' => [
        'visible' => [
          ':input[name="properties[options][webform_quiz_element_mode]"]' => ['value' => QuizWebformElementBaseInterface::WEBFORM_ELEMENT_MODE_SURVEY],
        ],
      ],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function validateWebformOptions(&$element, FormStateInterface $form_state, &$complete_form) {
    parent::validateWebformOptions($element, $form_state, $complete_form);

    // Make sure there is only one correct answer.
    $values = $form_state->getValues();

    if($values['properties']['options']['webform_quiz_element_mode'] === 'quiz') {
      $items = $values['properties']['options']['items'];
      $correct_items = [];

      foreach ($items as $item) {
        if (isset($item['is_correct_answer']) && $item['is_correct_answer']) {
          $correct_items[] = $item;
        }
      }

      $num_correct_items = count($correct_items);
      $allowed = $values['properties']['multiple'];
      if ($values['properties']['multiple'] === FALSE) {
        $allowed = 1;
      }
      if ($values['properties']['multiple'] === TRUE) {
        $allowed = PHP_INT_MAX;
      }
      if ($num_correct_items > $allowed) {
        $form_state->setError($element, t('Only @count choice can be the correct answer.', ['@count' => $allowed]));
      }
      elseif (!$num_correct_items) {
        $form_state->setError($element, t('Please select a correct answer.'));
      }
    }
  }


  /* ************************************************************************ */
  // Helper functions.
  /* ************************************************************************ */

  /**
   * Convert values from yamform_multiple element to options.
   *
   * @param array $values
   *   An array of values.
   * @param bool $options_description
   *   Options has description.
   *
   * @return array
   *   An array of options.
   */
  public static function convertValuesToOptions(array $values = NULL, $options_description = FALSE) {
    $options = [];
    if ($values && is_array($values)) {
      foreach ($values as $option_value => $option) {
        @$option['description'] = $options_description ? @$option['description'] : '';
        $option_text = json_encode($option);

        // Populate empty option value or option text.
        if ($option_value === '') {
          $option_value = @$option['text'];
        }
        elseif ($option_text === '') {
          $option_text = $option_value;
        }

        $options[$option_value] = $option_text;
      }
    }
    return $options;
  }

  /**
   * Convert options to values for webform_multiple element.
   *
   * @param array $options
   *   An array of options.
   * @param bool $options_description
   *   Options has description.
   *
   * @return array
   *   An array of values.
   */
  public static function convertOptionsToValues(array $options = [], $options_description = FALSE) {
    $values = [];
    foreach ($options as $value => $text) {
      $values[$value] = json_decode($text, TRUE);
      @$values[$value]['description'] = $options_description ? @$values[$value]['description'] : '';
    }
    return $values;
  }



}
