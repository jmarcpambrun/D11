<?php

namespace Drupal\webform_quiz_image_select\Element;

use Drupal\webform_image_select\Element\WebformImageSelectImages;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form element to assist in creation of webform select image images.
 *
 * @FormElement("webform_quiz_image_select_images")
 */
class WebformQuizImageSelectImages extends WebformImageSelectImages {

  /**
   * Process images and build images widget.
   */
  public static function processWebformImageSelectImages(&$element, FormStateInterface $form_state, &$complete_form) {
    parent::processWebformImageSelectImages($element, $form_state, $complete_form);

    $element['images']['#header'][] = ['data' => t('Right ?'), 'width' => '10%'];

    $element['images']['#element']['is_correct_answer'] = [
      '#type' => 'checkbox',
      '#title' => t('Is this the correct answer?'),
    ];

    return $element;
  }

}
