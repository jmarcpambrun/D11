<?php

namespace Drupal\burndown\Form;

use Drupal\burndown\Entity\Sprint;
use Drupal\burndown\Entity\Swimlane;
use Drupal\burndown\Entity\Task;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides a form for closing Sprints.
 */
class SprintCloseForm extends FormBase {

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'burndown_sprint_close_form';
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param int $sprint_id
   *   The entity ID of the Sprint to be loaded.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $sprint_id = NULL) {

    // Load the Sprint.
    $sprint = Sprint::load($sprint_id);
    if ($sprint === FALSE) {
      throw new NotFoundHttpException();
    }

    // Ensure sprint isn't already completed.
    if ($sprint->getStatus() == 'completed') {
      throw new NotFoundHttpException();
    }

    // Get tasks for the sprint, and see if any are open.
    $is_open = FALSE;
    $project = $sprint->getProject();
    $shortcode = $project->getShortcode();
    $tasks = Task::getTasksForBacklogSprint($shortcode, $sprint_id);

    if ($tasks !== FALSE) {
      foreach ($tasks as $task) {
        if (!$task->isCompleted()) {
          $is_open = TRUE;
          break;
        }
      }
    }

    // Check if the project has an upcoming sprint.
    $has_upcoming_sprint = (Sprint::getTopBacklogSprintFor($shortcode) !== FALSE);

    // Prefix/suffix to allow modal reloading.
    $form['#prefix'] = '<div id="close_sprint_form">';
    $form['#suffix'] = '</div>';

    // The status messages that will contain any form errors.
    $form['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -10,
    ];

    if ($is_open) {
      $form['still_open'] = [
        '#type' => 'item',
        '#markup' => $this->t('<b>Warning:</b> There are tasks on this sprint that are still open.'),
      ];

      if ($has_upcoming_sprint) {
        $form['still_open2'] = [
          '#type' => 'item',
          '#markup' => $this->t('You can either put them in the backlog, or into the upcoming sprint (or close this window to cancel the operation.'),
        ];

        $form['resolution_status'] = [
          '#type' => 'select',
          '#title' => $this->t('Resolution Status'),
          '#options' => [
            'backlog' => $this->t('Put open tickets in the backlog'),
            'next_sprint' => $this->t('Put open tickets in the next sprint (if there is one)'),
          ],
          '#empty_value' => '',
          '#empty_option' => $this->t('-- Select --'),
          '#required' => TRUE,
        ];
      }
      else {
        $form['still_open2'] = [
          '#type' => 'item',
          '#markup' => $this->t('If you continue, the open tickets will be placed back in the backlog. Alternatively, close this window to cancel the operation.'),
        ];

        $form['resolution_status'] = [
          '#type' => 'hidden',
          '#value' => 'backlog',
        ];
      }
    }
    else {
      $form['description'] = [
        '#type' => 'item',
        '#markup' => $this->t('Please "Close Sprint" to close this sprint and move all tasks into the Completed Tasks board.'),
      ];

      $form['resolution_status'] = [
        '#type' => 'hidden',
        '#value' => 'close_tickets',
      ];
    }

    $form['sprint_id'] = [
      '#type' => 'hidden',
      '#value' => $sprint_id,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['send'] = [
      '#type' => 'submit',
      '#value' => $this->t('Close sprint'),
      '#attributes' => [
        'class' => [
          'use-ajax',
        ],
      ],
      '#ajax' => [
        'callback' => [
          $this,
          'submitModalFormAjax',
        ],
        'event' => 'click',
      ],
    ];

    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    return $form;
  }

  /**
   * AJAX callback handler that displays any errors or a success message.
   */
  public function submitModalFormAjax(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    // If there are any form errors, re-display the form.
    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new ReplaceCommand('#close_sprint_form', $form));
    }
    else {
      // Retrieve sprint id.
      $sprint_id = $form_state->getValue('sprint_id');

      // Retrieve resolution status.
      $resolution_status = $form_state->getValue('resolution_status');

      // Get project info (for redirection) from task.
      $sprint = Sprint::load($sprint_id);
      $project = $sprint->getProject();
      $shortcode = $project->getShortcode();
      $result = '/burndown/backlog/' . $shortcode;

      // Get completed column.
      $completed_lane = Swimlane::getCompletedSwimlanes($shortcode);
      if ($completed_lane !== FALSE) {
        $completed_lane = reset($completed_lane);
      }
      else {
        // This is an error condition, but an admin will need to
        // fix it!
        $this->messenger()->addMessage($this->t('There is no completed column for this project. Please contact your system administrator to fix this problem. The task cannot be closed.'));
        $response->addCommand(new RedirectCommand($result));
        $form_state->setResponse($response);
        return $response;
      }

      // Get backlog, in case required.
      $backlog = Swimlane::getBacklogFor($shortcode);

      // Get upcoming sprint, in case required.
      $upcoming_sprint = Sprint::getTopBacklogSprintFor($shortcode);
      $has_upcoming_sprint = ($upcoming_sprint !== FALSE);

      // Close tasks.
      $tasks = Task::getTasksForBacklogSprint($project->getShortcode(), $sprint_id);

      if ($tasks !== FALSE) {
        foreach ($tasks as $task) {
          if ($task->isCompleted() ||
            ($resolution_status == 'close_tickets')) {

            // Close the task.
            $task
              ->setCompleted(TRUE)
              ->setSwimlane($completed_lane)
              ->save();
          }
          else {
            if ($resolution_status == 'backlog' ||
              !$has_upcoming_sprint) {

              // Send the task back to the backlog.
              $task
                ->setSwimlane($backlog)
                ->set('sprint', NULL)
                ->save();
            }
            else {
              // Send the task to the next sprint in the backlog.
              $task
                ->setSwimlane($backlog)
                ->set('sprint', $upcoming_sprint)
                ->save();
            }
          }
        }
      }

      // Close the sprint itself.
      $sprint->closeSprint();

      // Display message.
      $this->messenger()->addMessage($this->t('The sprint has been closed.'));

      // Reload the board.
      $response->addCommand(new RedirectCommand($result));
      $form_state->setResponse($response);
    }

    return $response;
  }

  /**
   * Ajax callback to close the modal dialog.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An Ajax Response object.
   */
  public function closeModalForm() {
    $command = new CloseModalDialogCommand();
    $response = new AjaxResponse();
    $response->addCommand($command);
    return $response;
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

}
