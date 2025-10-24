<?php

namespace Drupal\burndown\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for editing the default swimlane list.
 */
class DefaultSwimlaneForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $default_swimlane = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $default_swimlane->label(),
      '#description' => $this->t("Label for the Default Swimlane."),
      '#required' => TRUE,
    ];

    $form['sort_order'] = [
      '#type' => 'number',
      '#title' => $this->t('Sort Order'),
      '#default_value' => $default_swimlane->getSortOrder(),
      '#min' => 0,
      '#description' => $this->t("Sort order that this swimlane will show (by default) on the board."),
      '#required' => TRUE,
    ];

    $form['show_backlog'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show in Backlog?'),
      '#default_value' => $default_swimlane->getShowBacklog(),
      '#description' => $this->t("Should this swimlane show in the Backlog?"),
    ];

    $form['show_project_board'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show on Project Board?'),
      '#default_value' => $default_swimlane->getShowProjectBoard(),
      '#description' => $this->t("Should this swimlane show on the Project Board?"),
    ];

    $form['show_completed'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show on Completed Board?'),
      '#default_value' => $default_swimlane->getShowCompleted(),
      '#description' => $this->t("Should this swimlane show on the Completed Tasks Board?"),
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $default_swimlane->id(),
      '#machine_name' => [
        'exists' => '\Drupal\burndown\Entity\DefaultSwimlane::load',
      ],
      '#disabled' => !$default_swimlane->isNew(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $default_swimlane = $this->entity;
    $default_swimlane->set('sort_order', $form_state->getValue('sort_order'));
    $default_swimlane->set('show_backlog', $form_state->getValue('show_backlog'));
    $default_swimlane->set('show_project_board', $form_state->getValue('show_project_board'));
    $default_swimlane->set('show_completed', $form_state->getValue('show_completed'));
    $status = $default_swimlane->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label Default Swimlane.', [
          '%label' => $default_swimlane->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label Default Swimlane.', [
          '%label' => $default_swimlane->label(),
        ]));
    }
    $form_state->setRedirectUrl($default_swimlane->toUrl('collection'));
  }

}
