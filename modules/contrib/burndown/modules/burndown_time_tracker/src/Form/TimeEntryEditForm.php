<?php

namespace Drupal\burndown_time_tracker\Form;

use Drupal\burndown\Entity\Task;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Modal form for editing a single Burndown work-log entry.
 */
class TimeEntryEditForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'burndown_time_tracker_time_entry_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?Task $burndown_task = NULL, ?int $delta = NULL) {
    if (!$burndown_task || $delta === NULL) {
      throw new NotFoundHttpException();
    }

    $log = $burndown_task->getLog();
    if (!isset($log[$delta]) || ($log[$delta]['type'] ?? '') !== 'work') {
      throw new NotFoundHttpException();
    }

    $entry = $log[$delta];
    $account = $this->currentUser();
    $entry_uid = isset($entry['uid']) && is_numeric($entry['uid']) ? (int) $entry['uid'] : 0;

    if (!$account->hasPermission('administer burndown') && (!$account->hasPermission('burndown comment on task') || $entry_uid !== (int) $account->id())) {
      throw new AccessDeniedHttpException();
    }

    $comment = '';
    if (isset($entry['comment'])) {
      if (is_array($entry['comment']) && isset($entry['comment']['value'])) {
        $comment = (string) $entry['comment']['value'];
      }
      elseif (is_scalar($entry['comment'])) {
        $comment = (string) $entry['comment'];
      }
    }

    $work_done = isset($entry['work_done']) ? (string) $entry['work_done'] : '';
    $amount = '';
    if (preg_match('/^([0-9]*\.?[0-9]+)/', trim($work_done), $matches)) {
      $amount = $matches[1];
    }

    $form['task_id'] = [
      '#type' => 'value',
      '#value' => (int) $burndown_task->id(),
    ];
    $form['delta'] = [
      '#type' => 'value',
      '#value' => (int) $delta,
    ];

    $form['comment'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Comment'),
      '#default_value' => $comment,
      '#required' => FALSE,
    ];

    $form['work'] = [
      '#type' => 'number',
      '#title' => $this->t('Work amount'),
      '#description' => $this->t('Time entries are tracked in hours only.'),
      '#step' => '0.01',
      '#min' => 0,
      '#default_value' => $amount,
      '#required' => TRUE,
    ];

    $form['work_unit'] = [
      '#type' => 'item',
      '#title' => $this->t('Work unit'),
      '#markup' => $this->t('Hours'),
    ];

    $form['work_increment'] = [
      '#type' => 'value',
      '#value' => 'h',
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
      '#attributes' => [
        'class' => ['use-ajax'],
      ],
      '#ajax' => [
        'callback' => '::ajaxClose',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!is_numeric($form_state->getValue('work'))) {
      $form_state->setErrorByName('work', $this->t('Work amount must be numeric.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $task = Task::load((int) $form_state->getValue('task_id'));
    $delta = (int) $form_state->getValue('delta');

    if (!$task) {
      throw new NotFoundHttpException();
    }

    $log = $task->getLog();
    if (!isset($log[$delta]) || ($log[$delta]['type'] ?? '') !== 'work') {
      throw new NotFoundHttpException();
    }

    $entry = $log[$delta];
    $account = $this->currentUser();
    $entry_uid = isset($entry['uid']) && is_numeric($entry['uid']) ? (int) $entry['uid'] : 0;

    if (!$account->hasPermission('administer burndown') && (!$account->hasPermission('burndown comment on task') || $entry_uid !== (int) $account->id())) {
      throw new AccessDeniedHttpException();
    }

    $log[$delta]['comment'] = $form_state->getValue('comment');
    $log[$delta]['work_done'] = $form_state->getValue('work') . 'h';
    $task->set('log', $log)->save();

    $this->messenger()->addStatus($this->t('Time entry updated.'));
    $destination = $this->getReturnDestination();
    if ($destination !== '') {
      $form_state->setRedirectUrl(Url::fromUserInput($destination));
      return;
    }

    $form_state->setRedirect('view.burndown_hours.hours');
  }

  /**
   * Ajax callback placeholder to keep modal submit working through core ajax.
   */
  public function ajaxClose(array &$form, FormStateInterface $form_state) {
    if ($form_state->hasAnyErrors()) {
      return $form;
    }

    $response = new AjaxResponse();
    $response->addCommand(new CloseModalDialogCommand());
    $destination = $this->getReturnDestination();
    $redirect = $destination !== ''
      ? $destination
      : Url::fromRoute('view.burndown_hours.hours')->toString();
    $response->addCommand(new RedirectCommand($redirect));
    return $response;
  }

  /**
   * Returns a validated internal destination URL, if provided.
   */
  protected function getReturnDestination() : string {
    $destination = (string) $this->getRequest()->query->get('destination', '');
    if ($destination === '') {
      return '';
    }

    if (UrlHelper::isExternal($destination) || !str_starts_with($destination, '/')) {
      return '';
    }

    return $destination;
  }

}
