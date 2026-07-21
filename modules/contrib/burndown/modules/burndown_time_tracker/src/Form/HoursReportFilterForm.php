<?php

namespace Drupal\burndown_time_tracker\Form;

use Drupal\burndown\Entity\Project;
use Drupal\burndown\Entity\Sprint;
use Drupal\user\Entity\User;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Filter form for the Burndown hours report.
 */
class HoursReportFilterForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'burndown_time_tracker_hours_report_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, int $selected_uid = 0, array $input = [], bool $is_admin = FALSE) {
    $action = Url::fromRoute('view.burndown_hours.hours')->toString();
    $date_start = isset($input['date_start']) ? (string) $input['date_start'] : '';
    $date_end = isset($input['date_end']) ? (string) $input['date_end'] : '';

    $project_options = ['' => $this->t('- Any -')];
    foreach (Project::loadMultiple() as $project) {
      $project_options[$project->id()] = $project->label();
    }

    $sprint_options = ['' => $this->t('- Any -')];
    foreach (Sprint::loadMultiple() as $sprint) {
      $sprint_options[$sprint->id()] = $sprint->label();
    }

    $form['#method'] = 'get';
    $form['#action'] = $action;
    $form['#attributes']['class'][] = 'burndown-hours-filters';

    $form['project'] = [
      '#type' => 'select',
      '#title' => $this->t('Project'),
      '#options' => $project_options,
      '#default_value' => isset($input['project']) ? (string) $input['project'] : '',
    ];

    $form['sprint'] = [
      '#type' => 'select',
      '#title' => $this->t('Sprint'),
      '#options' => $sprint_options,
      '#default_value' => isset($input['sprint']) ? (string) $input['sprint'] : '',
    ];

    if ($is_admin) {
      $form['tracked_user'] = [
        '#type' => 'entity_autocomplete',
        '#title' => $this->t('User'),
        '#target_type' => 'user',
        '#default_value' => $selected_uid ? User::load($selected_uid) : NULL,
      ];
    }
    else {
      $form['tracked_user'] = [
        '#type' => 'value',
        '#value' => $selected_uid,
      ];
    }

    $form['date_start'] = [
      '#type' => 'date',
      '#title' => $this->t('Start date'),
      '#default_value' => $date_start,
    ];

    $form['date_end'] = [
      '#type' => 'date',
      '#title' => $this->t('End date'),
      '#default_value' => $date_end,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
      '#button_type' => 'primary',
    ];
    $form['actions']['reset'] = [
      '#type' => 'link',
      '#title' => $this->t('Reset'),
      '#url' => Url::fromRoute('view.burndown_hours.hours'),
      '#attributes' => [
        'class' => ['button'],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $query = [
      'project' => $form_state->getValue('project'),
      'sprint' => $form_state->getValue('sprint'),
      'date_start' => $form_state->getValue('date_start'),
      'date_end' => $form_state->getValue('date_end'),
    ];

    if ($form_state->getValue('tracked_user')) {
      $tracked_user = $form_state->getValue('tracked_user');
      $query['tracked_user'] = is_object($tracked_user) ? $tracked_user->id() : $tracked_user;
    }

    $form_state->setRedirect('view.burndown_hours.hours', [], ['query' => array_filter($query, static fn ($value) => $value !== NULL && $value !== '')]);
  }

}
