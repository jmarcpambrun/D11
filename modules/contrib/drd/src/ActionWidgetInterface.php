<?php

namespace Drupal\drd;

use Drupal\Core\Form\FormStateInterface;
use Drupal\drd\Entity\BaseInterface;
use Drupal\system\ActionConfigEntityInterface;

/**
 * Interface for DRD action widget services.
 *
 * @package Drupal\drd
 */
interface ActionWidgetInterface {

  /**
   * Set the action manager mode.
   *
   * @param string $mode
   *   The mode.
   *
   * @return $this
   */
  public function setMode(string $mode): self;

  /**
   * Get all action plugins.
   *
   * @return \Drupal\drd\Plugin\Action\BaseInterface[]
   *   All action plugins depending on mode and/or term.
   */
  public function getActionPlugins(): array;

  /**
   * Get the selected action.
   *
   * @return \Drupal\system\ActionConfigEntityInterface
   *   The selected action.
   */
  public function getSelectedAction(): ActionConfigEntityInterface;

  /**
   * Get the number of executed actions.
   *
   * @return int
   *   Number of executed actions.
   */
  public function getExecutedCount(): int;

  /**
   * Build a settings form for remote actions.
   *
   * @param array $form
   *   The form array .
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param array $options
   *   Options for the form.
   */
  public function buildForm(array &$form, FormStateInterface $form_state, array $options = []): void;

  /**
   * Validate the action settings.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void;

  /**
   * Set the selected entities for execution.
   *
   * @param \Drupal\drd\Entity\BaseInterface|\Drupal\drd\Entity\BaseInterface[] $entities
   *   The selected entities.
   *
   * @return $this
   */
  public function setSelectedEntities(BaseInterface|array $entities): self;

  /**
   * Submit the action execution form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void;

}
