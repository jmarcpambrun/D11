<?php

namespace Drupal\webform_quiz\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines an interface for Webform quiz submit handler plugins.
 */
interface WebformQuizSubmitHandlerInterface extends PluginInspectionInterface {

  /**
   * Perform actions for a submit handler.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   FormState object.
   */
  public function handleSubmit(array &$form, FormStateInterface $form_state);

}
