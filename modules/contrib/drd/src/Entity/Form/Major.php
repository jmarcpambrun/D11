<?php

namespace Drupal\drd\Entity\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Major Version edit forms.
 *
 * @ingroup drd
 */
class Major extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $entity = $this->entity;
    $status = parent::save($form, $form_state);

    if ($status === SAVED_NEW) {
      $this->messenger()
        ->addMessage($this->t('Created the @label Major Version.', [
          '@label' => $entity->label(),
        ]));
    }
    else {
      $this->messenger()
        ->addMessage($this->t('Saved the @label Major Version.', [
          '@label' => $entity->label(),
        ]));
    }
    $form_state->setRedirect('entity.drd_major.canonical', ['drd_major' => $entity->id()]);
    return $status;
  }

}
