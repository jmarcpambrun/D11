<?php

namespace Drupal\drd\Entity\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Release edit forms.
 *
 * @ingroup drd
 */
class Release extends ContentEntityForm {

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $entity = $this->entity;
    $status = $entity->save();

    if ($status === SAVED_NEW) {
      $this->messenger()->addMessage($this->t('Created the @label Release.', [
        '@label' => $entity->label(),
      ]));

    }
    else {
      $this->messenger()->addMessage($this->t('Saved the @label Release.', [
        '@label' => $entity->label(),
      ]));
    }
    $form_state->setRedirect('entity.drd_release.canonical', ['drd_release' => $entity->id()]);
    return $status;
  }

}
