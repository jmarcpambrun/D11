<?php

namespace Drupal\burndown\Form;

use Drupal\burndown\Entity\Swimlane;
use Drupal\burndown\Entity\Task;
use Drupal\burndown\Event\TaskClosedEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides a form for closing Tasks.
 */
class TaskCloseForm extends FormBase {

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\ContainerAwareEventDispatcher
   * $event_dispatcher.
   */
  protected $eventDispatcher;

  /**
   * Class constructor.
   */
  public function __construct(EventDispatcherInterface $eventDispatcher) {
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('event_dispatcher')
    );
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'burndown_task_close_form';
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param int|null $ticket_id
   *   The id of the ticket from which to load the Task.
   * @param string $board
   *   The name of the Board.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $ticket_id = NULL, $board = 'board') {

    // Load the task.
    $task = Task::loadFromTicketId($ticket_id);
    if ($task === FALSE) {
      // Task doesn't exist; throw 404.
      throw new NotFoundHttpException();
    }

    // Prefix/suffix to allow modal reloading.
    $form['#prefix'] = '<div id="close_task_form">';
    $form['#suffix'] = '</div>';

    // The status messages that will contain any form errors.
    $form['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -10,
    ];

    $form['description'] = [
      '#type' => 'item',
      '#markup' => $this->t('<h2>Close Task @ticket_id ?</h2>', [
        '@ticket_id' => $ticket_id,
      ]),
    ];

    $form['ticket_id'] = [
      '#type' => 'hidden',
      '#value' => $ticket_id,
    ];

    $form['board'] = [
      '#type' => 'hidden',
      '#value' => $board,
    ];

    $form['resolution_status'] = [
      '#type' => 'select',
      '#title' => $this->t('Resolution Status'),
      '#options' => Task::getResolutionStatuses(),
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['send'] = [
      '#type' => 'submit',
      '#value' => $this->t('Close task'),
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
      $response->addCommand(new ReplaceCommand('#close_task_form', $form));
    }
    else {
      // Retrieve task id.
      $ticket_id = $form_state->getValue('ticket_id');

      // Board to redirect back to.
      $board = $form_state->getValue('board');

      // Retrieve resolution status.
      $resolution_status = $form_state->getValue('resolution_status');

      // Get project info (for redirection) from task.
      $task = Task::loadFromTicketId($ticket_id);
      $project = $task->getProject();
      $shortcode = $project->getShortcode();
      $result = '/burndown/' . $board . '/' . $shortcode;

      // Get completed swimlane.
      $completed_lane = Swimlane::getCompletedSwimlanes($shortcode);
      if ($completed_lane !== FALSE) {
        $completed_lane = reset($completed_lane);
      }
      else {
        // This is an error condition, but an admin will need to
        // fix it!
        $this->messenger()->addMessage($this->t('There is no completed swimlane for this project. Please contact your system administrator to fix this problem. The task cannot be closed.'));
        $response->addCommand(new RedirectCommand($result));
        $form_state->setResponse($response);
        return $response;
      }

      // Update the task.
      $task
        ->setCompleted(TRUE)
        ->setResolution($resolution_status)
        ->setSwimlane($completed_lane)
        ->save();

      // Issue a TaskClosedEvent event.
      $event = new TaskClosedEvent($task);

      // Dispatch the event.
      $this
        ->eventDispatcher
        ->dispatch($event, TaskClosedEvent::CLOSED);

      // Display message.
      $this->messenger()->addMessage($this->t('Task %ticket_id has been closed.', [
        '%ticket_id' => $ticket_id,
      ]));

      // Reload the board.
      $response->addCommand(new RedirectCommand($result));
      $form_state->setResponse($response);
    }

    return $response;
  }

  /**
   * Close the modal form.
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
