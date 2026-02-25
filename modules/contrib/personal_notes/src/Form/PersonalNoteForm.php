<?php

namespace Drupal\personal_notes\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for creating/editing personal_notes entities.
 */
class PersonalNoteForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'personal_note_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $form = parent::create($container);
    $form->setMessenger($container->get('messenger'));
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, AccountInterface $user = NULL) {
    $form = parent::buildForm($form, $form_state);

    if (!empty($user)) {

      $form['note_user'] = [
        '#type' => 'markup',
        '#markup' => "User: " . $user->getAccountName(),
      ];

      $form['user']['#type'] = 'hidden';
      $form['user']['widget'][0]['target_id']['#default_value'] = $user;

    }
    return $form;

  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $status = parent::save($form, $form_state);
    if ($status == SAVED_NEW) {
      $this->messenger->addMessage(t('Personal Note %label has been created.', ['%label' => $this->entity->label()]));
    }
    else {
      $this->messenger->addMessage(t('Personal Note %label has been updated.', ['%label' => $this->entity->label()]));
    }

    $user = $form_state->getValue('user')[0]['target_id'];
    $form_state->setRedirect('view.user_personal_notes.page_1', ['user' => $user]);
  }

}

