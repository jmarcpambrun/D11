<?php

namespace Drupal\eca_webform\Event;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides submission form alter event for eca_webform.
 *
 * @package Drupal\eca_webform\Event
 */
class SubmissionFormAlter extends WebformBaseEvent {

  /**
   * String representing the webform's id.
   *
   * @var string
   */
  protected string $formId;

  /**
   * Constructs the SubmissionFormAlter event.
   *
   * @param array $form
   *   Nested array of form elements that comprise the webform.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $form_id
   *   String representing the webform's id.
   */
  public function __construct(array &$form, FormStateInterface $form_state, string $form_id) {
    $this->formId = $form_id;
    parent::__construct($form, $form_state);
  }

  /**
   * String representing the webform's id.
   *
   * @return string
   *   String representing the webform's id.
   */
  public function getFormId(): string {
    return $this->formId;
  }

}
