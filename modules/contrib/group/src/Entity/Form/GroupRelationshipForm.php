<?php

namespace Drupal\group\Entity\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the relationship edit forms.
 *
 * @ingroup group
 */
class GroupRelationshipForm extends ContentEntityForm {

  /**
   * The entity being used by this form.
   *
   * @var \Drupal\group\Entity\GroupRelationshipInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    // Do not allow to edit the relationship subject through the UI.
    if ($this->operation !== 'add') {
      $form['entity_id']['#access'] = FALSE;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $return = parent::save($form, $form_state);

    // The below redirect ensures the user will be redirected to something they
    // can view in the following order: The relationship, the target entity
    // itself, the group and finally the front page. This only applies if there
    // was no destination GET parameter set in the URL.
    if ($this->entity->access('view')) {
      $form_state->setRedirectUrl($this->entity->toUrl());
    }
    elseif ($this->entity->getEntity()->access('view')) {
      $form_state->setRedirectUrl($this->entity->getEntity()->toUrl());
    }
    elseif ($this->entity->getGroup()->access('view')) {
      $form_state->setRedirectUrl($this->entity->getGroup()->toUrl());
    }
    else {
      $form_state->setRedirect('<front>');
    }

    return $return;
  }

}
