<?php

namespace Drupal\drd\Entity\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Domain edit forms.
 *
 * @ingroup drd
 */
class Domain extends ContentEntityForm {

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $entity = $this->entity;
    $status = $entity->save();
    $this->messenger()->addMessage($this->t('Saved the @label Domain.', [
      '@label' => $entity->label(),
    ]));
    $form_state->setRedirect('entity.drd_domain.canonical', ['drd_domain' => $entity->id()]);
    return $status;
  }

}
