<?php

namespace Drupal\eca_webform\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Event\FormEventInterface;

/**
 * Abstract base event for all webform events.
 *
 * @package Drupal\eca_webform\Event
 */
abstract class WebformBaseEvent extends Event implements FormEventInterface, WebformEventInterface {

  /**
   * The form array.
   *
   * This may be the complete form, or a sub-form, or a specific form element.
   *
   * @var array
   */
  protected array $form;

  /**
   * The form state.
   *
   * @var \Drupal\Core\Form\FormStateInterface
   */
  protected FormStateInterface $formState;

  /**
   * Constructs a FormBase instance.
   *
   * @param array &$form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function __construct(array &$form, FormStateInterface $form_state) {
    $this->form = &$form;
    $this->formState = $form_state;
  }

  /**
   * {@inheritdoc}
   */
  public function &getForm(): array {
    return $this->form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormState(): FormStateInterface {
    return $this->formState;
  }

}
