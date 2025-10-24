<?php

namespace Drupal\mailboxlayer\Validate;

use Drupal\Core\Form\FormStateInterface;

/**
 * Form API callback. Validate the email id using mailboxlayer.
 */
class MailboxlayerConstraint {

  /**
   * Validates given element.
   *
   * @param array $element
   *   The form element to process.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   \Drupal\Core\Form\FormStateInterface $formState.
   * @param array $form
   *   The complete form structure.
   */
  public static function validateEmail(array &$element, FormStateInterface $form_state, array &$form) {
    // Getting formObject.
    $formObject = $form_state->getFormObject();
    if (is_object($formObject)) {
      $webform = $formObject->getWebform();
      if (is_object($webform)) {
        // Getting list of all the fields present in the webform.
        $webformElements = $webform->getElementsInitializedAndFlattened();
        // Iterating throught the fields to identify the email field key.
        $keyList = [];
        foreach ($webformElements as $key => $value) {
          if ($value['#type'] == 'email') {
            $keyList[$key] = $form_state->getValue($key);
          }
        }
        foreach ($keyList as $key => $value) {
          $emailStatus = mailboxlayer_validate($value);
          if (!$emailStatus) {
            $form_state->setErrorByName($key, t('Email ID is invalid'));
          }
        }
      }
    }
  }

}
