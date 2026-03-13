<?php

namespace Drupal\drd\Entity\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Requirement edit forms.
 *
 * @ingroup drd
 */
class Requirement extends ContentEntityForm {

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $requirement = $this->entity;
    $status = $requirement->save();

    if ($status === SAVED_NEW) {
      $this->messenger()
        ->addMessage($this->t('Created the @label Requirement.', [
          '@label' => $requirement->label(),
        ]));
    }
    else {
      $this->messenger()->addMessage($this->t('Saved the @label Requirement.', [
        '@label' => $requirement->label(),
      ]));
    }
    $form_state->setRedirect('entity.drd_requirement.canonical', ['drd_requirement' => $requirement->id()]);
    return $status;
  }

}
