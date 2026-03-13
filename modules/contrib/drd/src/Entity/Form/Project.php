<?php

namespace Drupal\drd\Entity\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Project edit forms.
 *
 * @ingroup drd
 */
class Project extends ContentEntityForm {

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $entity = $this->entity;
    $status = $entity->save();

    if ($status === SAVED_NEW) {
      $this->messenger()->addMessage($this->t('Created the @label Project.', [
        '@label' => $entity->label(),
      ]));

    }
    else {
      $this->messenger()->addMessage($this->t('Saved the @label Project.', [
        '@label' => $entity->label(),
      ]));
    }
    $form_state->setRedirect('entity.drd_project.canonical', ['drd_project' => $entity->id()]);
    return $status;
  }

}
