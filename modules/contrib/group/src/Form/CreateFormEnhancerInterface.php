<?php

namespace Drupal\group\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\group\Entity\GroupInterface;

/**
 * Interface for a create form enhancer.
 */
interface CreateFormEnhancerInterface {

  /**
   * Checks whether a group or entity form was enhanced by this service.
   *
   * @param array $form
   *   The form array to check.
   *
   * @return bool
   *   Whether the form was enhanced.
   */
  public function isFormEnhanced(array $form): bool;

  /**
   * Enhances a group form submit.
   *
   * Whatever you do in ::enhanceGroupForm() probably needs to be taken care of
   * in a custom submit handler. This method allows you to alter the group form
   * submit element for that purpose.
   *
   * @param array $submit
   *   The submit element.
   */
  public function enhanceGroupFormSubmit(array &$submit): void;

  /**
   * Enhances a group form with a membership form.
   *
   * This should add the necessary fields and handlers to complete the group
   * along with its group membership in one submit. If no custom fields have
   * been added to the group relationship type, then no form should be added.
   *
   * @param array $form
   *   The group form to enhance.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the form to enhance.
   * @param array $relationship_values
   *   (optional) Additional values to instantiate the relationship with.
   */
  public function enhanceGroupForm(array &$form, FormStateInterface $form_state, array $relationship_values = []): void;

  /**
   * Enhances an entity form submit.
   *
   * Whatever you do in ::enhanceEntityForm() probably needs to be taken care of
   * in a custom submit handler. This method allows you to alter the entity form
   * submit element for that purpose.
   *
   * @param array $submit
   *   The submit element.
   */
  public function enhanceEntityFormSubmit(array &$submit): void;

  /**
   * Enhances an entity form with a relationship form.
   *
   * This should add the necessary fields and handlers to complete the entity
   * along with its group relationship in one submit. If no custom fields have
   * been added to the group relationship type, then no form should be added.
   *
   * @param array $form
   *   The group form to enhance.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the form to enhance.
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group to create the entity in.
   * @param string $plugin_id
   *   The plugin to use for the creation process.
   * @param array $relationship_values
   *   (optional) Additional values to instantiate the relationship with.
   */
  public function enhanceEntityForm(array &$form, FormStateInterface $form_state, GroupInterface $group, string $plugin_id, array $relationship_values = []): void;

}
