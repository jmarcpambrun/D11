<?php

namespace Drupal\burndown\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides an entity form for creating/editing Project types.
 */
class ProjectTypeForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $burndown_project_type = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $burndown_project_type->label(),
      '#description' => $this->t("Label for the Project type."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $burndown_project_type->id(),
      '#machine_name' => [
        'exists' => '\Drupal\burndown\Entity\ProjectType::load',
      ],
      '#disabled' => !$burndown_project_type->isNew(),
    ];

    /* You will need additional form elements for your custom properties. */

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $burndown_project_type = $this->entity;
    $status = $burndown_project_type->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label Project type.', [
          '%label' => $burndown_project_type->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label Project type.', [
          '%label' => $burndown_project_type->label(),
        ]));
    }
    $form_state->setRedirectUrl($burndown_project_type->toUrl('collection'));
  }

}
