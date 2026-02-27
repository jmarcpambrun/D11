<?php

namespace Drupal\webform_quiz_image_select\Element;

use Drupal\webform_image_select\Element\WebformImageSelectElementImages;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form element for managing webform element options.
 *
 * This element is used by select, radios, checkboxes, likert, and
 * mapping elements.
 *
 * @FormElement("webform_quiz_image_select_element_images")
 */
class WebformQuizImageSelectElementImages extends WebformImageSelectElementImages {

  /**
   * Processes a webform element image select images element.
   */
  public static function processWebformImageSelectElementImages(&$element, FormStateInterface $form_state, &$complete_form) {
    // Custom images.
    parent::processWebformImageSelectElementImages($element, $form_state, $complete_form);
    $element['custom']['#type'] = 'webform_quiz_image_select_images';
    return $element;
  }

}
