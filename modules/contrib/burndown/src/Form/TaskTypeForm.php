<?php

namespace Drupal\burndown\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides an entity form for creating/editing Task types.
 */
class TaskTypeForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $burndown_task_type = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $burndown_task_type->label(),
      '#description' => $this->t("Label for the Task type."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $burndown_task_type->id(),
      '#machine_name' => [
        'exists' => '\Drupal\burndown\Entity\TaskType::load',
      ],
      '#disabled' => !$burndown_task_type->isNew(),
    ];

    /* You will need additional form elements for your custom properties. */

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $burndown_task_type = $this->entity;
    $status = $burndown_task_type->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label Task type.', [
          '%label' => $burndown_task_type->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label Task type.', [
          '%label' => $burndown_task_type->label(),
        ]));
    }
    $form_state->setRedirectUrl($burndown_task_type->toUrl('collection'));
  }

}
