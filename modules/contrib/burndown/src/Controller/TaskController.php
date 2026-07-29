<?php

namespace Drupal\burndown\Controller;

use Drupal\burndown\Entity\Swimlane;
use Drupal\burndown\Entity\Task;
use Drupal\burndown\Entity\TaskInterface;
use Drupal\burndown\Event\TaskCommentEvent;
use Drupal\burndown\Event\TaskWorkEvent;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class TaskController.
 *
 *  Returns responses for Task routes.
 */
class TaskController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcher
   */
  protected $eventDispatcher;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->account = $container->get('current_user');
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->messenger = $container->get('messenger');
    $instance->renderer = $container->get('renderer');
    $instance->eventDispatcher = $container->get('event_dispatcher');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityTypeBundleInfo = $container->get('entity_type.bundle.info');
    return $instance;
  }

  /**
   * Custom access check for task view operations.
   *
   * @param string $ticket_id
   *   The ticket ID route parameter.
   * @param string $from_ticket_id
   *   The from-ticket route parameter used by relationship routes.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The user account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function checkTaskViewAccess($ticket_id = '', $from_ticket_id = '', ?AccountInterface $account = NULL) {
    $account = $account ?: $this->account;
    // Start with the broad perms.
    $access_perms = [
      'access burndown board',
      'access burndown',
      'access completed board',
      'add task entities',
      'administer task entities',
      'delete all task revisions',
      'delete task entities',
      'edit task entities',
      'modify sprint tasks',
      'revert all task revisions',
      'view all task revisions',
      'view published task entities',
      'view unpublished task entities',
    ];

    $task = $this->loadTaskForAccess($ticket_id, $from_ticket_id);
    if ($task) {
      // Be specific about the Project ID perms.
      $project_id = $task->getProjectId();
      $access_perms[] = "{$project_id} create entities";
      $access_perms[] = "{$project_id} edit any entities";
      $access_perms[] = "{$project_id} edit own entities";
      $access_perms[] = "{$project_id} view project";
    }
    else {
      // This is not a specific task, so go broad and see if they have ANY
      // project view perms.
      $projects = \Drupal::entityTypeManager()->getStorage('burndown_project')->loadMultiple();
      foreach ($projects as $project_id => $project) {
        $access_perms[] = "{$project_id} create entities";
        $access_perms[] = "{$project_id} edit any entities";
        $access_perms[] = "{$project_id} edit own entities";
        $access_perms[] = "{$project_id} view project";
      }
    }

    $task_access = AccessResult::allowedIfHasPermissions($account, $access_perms, 'OR');
    if ($task_access->isNeutral()) {
      return AccessResult::forbidden()->addCacheableDependency($task_access);
    }

    return $task_access;
  }

  /**
   * Custom access check for task add operations.
   *
   * @param string $ticket_id
   *   The ticket ID route parameter.
   * @param string $from_ticket_id
   *   The from-ticket route parameter used by relationship routes.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The user account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function checkTaskAddAccess($ticket_id = '', $from_ticket_id = '', ?AccountInterface $account = NULL) {
    $account = $account ?: $this->account;
    // Start with the broad perms.
    $access_perms = [
      'access burndown board',
      'access burndown',
      'access completed board',
      'add task entities',
      'administer task entities',
      'delete all task revisions',
      'delete task entities',
      'edit task entities',
      'modify sprint tasks',
      'revert all task revisions',
    ];

    $task = $this->loadTaskForAccess($ticket_id, $from_ticket_id);
    if ($task) {
      // Be specific about the Project ID perms.
       $project_id = $task->getProjectId();
       $access_perms[] = "{$project_id} create entities";
       $access_perms[] = "{$project_id} edit any entities";
       $access_perms[] = "{$project_id} edit own entities";
    }

    $task_access = AccessResult::allowedIfHasPermissions($account, $access_perms, 'OR');
    if ($task_access->isNeutral()) {
      return AccessResult::forbidden()->addCacheableDependency($task_access);
    }

    return $task_access;
  }

  /**
   * Custom access check for task edit operations.
   *
   * @param string $ticket_id
   *   The ticket ID route parameter.
   * @param string $from_ticket_id
   *   The from-ticket route parameter used by relationship routes.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The user account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function checkTaskEditAccess($ticket_id = '', $from_ticket_id = '', ?AccountInterface $account = NULL) {
    $account = $account ?: $this->account;
    $task = $this->loadTaskForAccess($ticket_id, $from_ticket_id);
    if (!$task) {
      return AccessResult::forbidden();
    }

    $project_id = $task->getProjectId();
    $access_perms = [
      'administer task entities',
      'edit task entities',
      'modify sprint tasks',
      "{$project_id} delete any entities",
      "{$project_id} delete own entities",
      "{$project_id} edit any entities",
      "{$project_id} edit own entities",
    ];
    $task_access = AccessResult::allowedIfHasPermissions($account, $access_perms, 'OR');
    if ($task_access->isNeutral()) {
      return AccessResult::forbidden()->addCacheableDependency($task_access);
    }

    return $task_access;
  }

  /**
   * Route-level access check for editing task log entries.
   *
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The user account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function checkTaskLogEditApiAccess(?AccountInterface $account = NULL) {
    $account = $account ?: $this->account;
    $task_access = AccessResult::allowedIfHasPermissions($account, [
      'administer burndown',
      'burndown comment on task',
    ], 'OR');

    if ($task_access->isNeutral()) {
      return AccessResult::forbidden()->addCacheableDependency($task_access);
    }

    return $task_access;
  }

  /**
   * Loads a task for route-based access checks.
   *
   * @param string $ticket_id
   *   The ticket ID route parameter.
   * @param string $from_ticket_id
   *   The from-ticket route parameter used by relationship routes.
   *
   * @return \Drupal\burndown\Entity\Task|false
   *   A task entity, or FALSE when not available.
   */
  protected function loadTaskForAccess($ticket_id = '', $from_ticket_id = '') {
    if (!empty($ticket_id)) {
      return Task::loadFromTicketId($ticket_id);
    }

    if (!empty($from_ticket_id)) {
      return Task::loadFromTicketId($from_ticket_id);
    }

    return FALSE;
  }

  /**
   * Get the log for a task (optionally filtered by type).
   *
   * @param int $ticket_id
   *   The Task ID.
   * @param string $type
   *   The type of log to return.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  public function getTaskLog($ticket_id, $type = 'all') {
    $data = [];

    $task = Task::loadFromTicketId($ticket_id);
    if ($task === FALSE) {
      // Task doesn't exist; throw 404.
      throw new NotFoundHttpException();
    }

    $log = $task->getLog();
    if (!empty($log)) {
      foreach ($log as $delta => $log_item) {
        if ($type !== 'all') {
          if ($log_item['type'] !== $type) {
            continue;
          }
        }

        $log_item['delta'] = $delta;
        $log_item['can_edit'] = $this->canEditTaskLogItem($log_item);

        // Get user name.
        $user = $this->entityTypeManager->getStorage('user')->load($log_item['uid']);
        $log_item['user'] = $user ? $user->getDisplayName() : $this->t('Anonymous');

        // Normalize log comment to a single string.
        $log_item['comment'] = $this->normalizeLogComment(isset($log_item['comment']) ? $log_item['comment'] : '');

        // Date format.
        $created_date = date('r', intval($log_item['created']));
        $log_item['created'] = $created_date;

        $data[] = $log_item;
      }
    }

    $build = [
      '#theme' => 'burndown_log_items',
      '#data' => $data,
    ];

    return new Response($this->renderer->render($build));
  }

  /**
   * Edit an existing comment or work-log entry on a task.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request containing task log update data.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response indicating success or failure.
   */
  public function editLog(Request $request) {
    $ticket_id = $request->request->get('ticket_id');
    $delta = $request->request->get('delta');
    $comment = $request->request->get('comment');
    $work = $request->request->get('work');
    $work_increment = $request->request->get('work_increment');

    if (!is_numeric($delta)) {
      throw new NotFoundHttpException();
    }

    $task = Task::loadFromTicketId($ticket_id);
    if ($task === FALSE) {
      throw new NotFoundHttpException();
    }

    $log = $task->getLog();
    $delta = (int) $delta;
    if (!isset($log[$delta])) {
      throw new NotFoundHttpException();
    }

    $log_item = $log[$delta];
    if (!$this->canEditTaskLogItem($log_item)) {
      throw new AccessDeniedHttpException();
    }

    $type = isset($log_item['type']) ? $log_item['type'] : '';
    $filtered_comment = Xss::filter((string) $comment);
    $log[$delta]['comment'] = $filtered_comment;

    if ($type === 'work' && ($work !== NULL || $work_increment !== NULL)) {
      if (!is_numeric($work)) {
        return new JsonResponse([
          'message' => 'Invalid work quantity',
          'success' => 0,
          'method' => 'POST',
        ], 400);
      }

      $allowed = ['m', 'h', 'd', 'w', 'M', 'Y'];
      if (!in_array($work_increment, $allowed, TRUE)) {
        return new JsonResponse([
          'message' => 'Invalid work increment',
          'success' => 0,
          'method' => 'POST',
        ], 400);
      }

      $log[$delta]['work_done'] = $work . $work_increment;
    }

    $task->set('log', $log)->save();

    return new JsonResponse([
      'success' => 1,
      'method' => 'POST',
    ]);
  }

  /**
   * Normalize burndown log comment payloads to a plain string.
   *
   * @param mixed $comment
   *   The comment payload from the burndown_log field.
   *
   * @return string
   *   The normalized comment value.
   */
  protected function normalizeLogComment($comment) {
    if (is_array($comment)) {
      if (isset($comment['value']) && is_scalar($comment['value'])) {
        return (string) $comment['value'];
      }

      return '';
    }

    if (is_scalar($comment)) {
      return (string) $comment;
    }

    return '';
  }

  /**
   * Determines whether the current user can edit a specific task log item.
   *
   * @param array $log_item
   *   A task log item payload.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   Optional account override.
   *
   * @return bool
   *   TRUE when the user can edit this log entry.
   */
  protected function canEditTaskLogItem(array $log_item, ?AccountInterface $account = NULL) {
    $account = $account ?: $this->account;

    $type = isset($log_item['type']) ? $log_item['type'] : '';
    if (!in_array($type, ['comment', 'work'], TRUE)) {
      return FALSE;
    }

    if ($account->hasPermission('administer burndown')) {
      return TRUE;
    }

    if (!$account->hasPermission('burndown comment on task')) {
      return FALSE;
    }

    if (!isset($log_item['uid']) || !is_numeric($log_item['uid'])) {
      return FALSE;
    }

    return (int) $log_item['uid'] === (int) $account->id();
  }

  /**
   * Add a comment to a Task.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The Request object from which to determine the Task to comment.
   */
  public function addComment(Request $request) {
    // Get data from request (validated below).
    $ticket_id = $request->request->get('ticket_id');
    $comment = $request->request->get('comment');

    // Load the task.
    $task = Task::loadFromTicketId($ticket_id);
    if ($task === FALSE) {
      // Task doesn't exist; throw 404.
      throw new NotFoundHttpException();
    }

    // Add comment to the log.
    $type = 'comment';
    $filtered_comment = Xss::filter($comment);
    $work_done = '';
    $created = time();
    $uid = $this->account->id();
    $description = '';

    $task
      ->addLog($type, $filtered_comment, $work_done, $created, $uid, $description)
      ->save();

    // Instantiate our event.
    $event = new TaskCommentEvent($task, $filtered_comment);

    // Dispatch the event.
	$this->eventDispatcher->dispatch($event, TaskCommentEvent::COMMENTED);

    // Return JSON response.
    return new JsonResponse([
      'success' => 1,
      'method' => 'POST',
    ]);
  }

  /**
   * Add a work log to the Task.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The Request object from which to determine the Task to add work to.
   */
  public function addWork(Request $request) {
    // Get data from request (validated below).
    $ticket_id = $request->request->get('ticket_id');
    $comment = $request->request->get('comment');
    $work = $request->request->get('work');
    $work_increment = $request->request->get('work_increment');

    // Load the task.
    $task = Task::loadFromTicketId($ticket_id);
    if ($task === FALSE) {
      // Task doesn't exist; throw 404.
      throw new NotFoundHttpException();
    }

    // Validate the $work is numeric.
    if (!is_numeric($work)) {
      throw new NotFoundHttpException();
    }

    // Validate that $work_increment is one of allowed list.
    $allowed = ['m', 'h', 'd', 'w', 'M', 'Y'];
    if (!in_array($work_increment, $allowed)) {
      throw new NotFoundHttpException();
    }

    // Add work to the log.
    $type = 'work';
    $filtered_comment = Xss::filter($comment);
    $work_done = $work . $work_increment;
    $created = time();
    $uid = \Drupal::currentUser()->id();
    $description = '';

    $task
      ->addLog($type, $filtered_comment, $work_done, $created, $uid, $description)
      ->save();

    // Instantiate our event.
    $event = new TaskWorkEvent($task, $filtered_comment, $work_done, $uid);

    // Dispatch the event.
    $this->eventDispatcher->dispatch($event, TaskWorkEvent::WORKED);

    // Return JSON response.
    return new JsonResponse([
      'success' => 1,
      'method' => 'POST',
    ]);
  }

  /**
   * Add a user to the task watchlist.
   *
   * @param string $ticket_id
   *   The Task ID.
   * @param int $user_id
   *   The User ID.
   */
  public function addToWatchlist(string $ticket_id, int $user_id) {
    // Load the task.
    /** @var \Drupal\burndown\Entity\TaskInterface $task */
    $task = Task::loadFromTicketId($ticket_id);
    if ($task === FALSE) {
      // Task doesn't exist; throw 404.
      throw new NotFoundHttpException();
    }

    // Load the user.
    $user = User::load($user_id);
    if ($user === FALSE) {
      throw new NotFoundHttpException();
    }

    // Check access using the shared task edit access checker.
    $access = $this->checkTaskEditAccess($ticket_id, '');
    if (!$access->isAllowed()) {
      throw new NotFoundHttpException();
    }

    // Add to watchlist.
    $task->addToWatchlist($user)->save();

    // Return JSON response.
    return new JsonResponse([
      'ticket_id' => $ticket_id,
      'user_id' => $user_id,
      'success' => 1,
      'method' => 'POST',
    ]);
  }

  /**
   * Remove a user from the task watchlist.
   *
   * @param string $ticket_id
   *   The Task ID.
   * @param int $user_id
   *   The User ID.
   */
  public function removeFromWatchlist(string $ticket_id, $user_id) {
    // Load the task.
    /** @var \Drupal\burndown\Entity\TaskInterface $task */
    $task = Task::loadFromTicketId($ticket_id);
    if ($task === FALSE) {
      // Task doesn't exist; throw 404.
      throw new NotFoundHttpException();
    }

    // Load the user.
    $user = User::load($user_id);
    if ($user === FALSE) {
      throw new NotFoundHttpException();
    }

    // Check access using the shared task edit access checker.
    $access = $this->checkTaskEditAccess($ticket_id, '');
    if (!$access->isAllowed()) {
      throw new NotFoundHttpException();
    }

    // Remove from watchlist.
    $task->removeFromWatchlist($user);

    // Return JSON response.
    return new JsonResponse([
      'ticket_id' => $ticket_id,
      'user_id' => $user_id,
      'success' => 1,
      'method' => 'POST',
    ]);
  }

  /**
   * Get list of relationships for a ticket for AJAX endpoint.
   *
   * @param string $ticket_id
   *   The Task ID.
   */
  public function getRelationships(string $ticket_id) {
    $data = [];

    $task = Task::loadFromTicketId($ticket_id);
    if ($task === FALSE) {
      return new JsonResponse([
        'message' => 'Task does not exist',
        'success' => 0,
        'method' => 'POST',
      ]);
    }
    $task_id = $task->id();
    $task_is_completed = $task->isCompleted();

    // Get relationships for the task.
    $relationships = $task->getRelationships();

    foreach ($relationships as $relationship) {
      $to_task = Task::load($relationship['task_id']);

      $data[] = [
        'local' => 1,
        'from_task_id' => $task_id,
        'from_ticket_id' => $ticket_id,
        'from_task_completed' => $task_is_completed,
        'to_task_id' => $relationship['task_id'],
        'to_ticket_id' => $to_task->getTicketId(),
        'to_task_completed' => $to_task->isCompleted(),
        'type' => $relationship['type'],
      ];
    }

    // Get back reference relationships.
    $back_relationships = $task->getRelationshipReferences();
    if (!empty($back_relationships)) {
      foreach ($back_relationships as $relationship) {
        $to_task = Task::load($relationship['task_id']);

        $data[] = [
          'local' => 0,
          'from_task_id' => $task_id,
          'from_ticket_id' => $ticket_id,
          'from_task_completed' => $task_is_completed,
          'to_task_id' => $relationship['task_id'],
          'to_task_completed' => $relationship['is_completed'],
          'to_ticket_id' => $to_task->getTicketId(),
          'type' => $relationship['type'],
        ];
      }
    }

    // Render partial.
    $build = [
      '#theme' => 'burndown_task_relationships',
      '#data' => $data,
    ];

    return new Response($this->renderer->render($build));

  }

  /**
   * Add a relationship between tasks.
   */
  public function addRelationship(Request $request) {
    // Get data from request (validated below).
    $from_ticket_id = $request->request->get('from_ticket_id');
    $to_ticket_id = $request->request->get('to_ticket_id');
    $type = $request->request->get('type');

    if ($from_ticket_id == $to_ticket_id) {
      return new JsonResponse([
        'message' => 'Task cannot be related to itself',
        'success' => 0,
        'method' => 'POST',
      ]);
    }

    // Load the tasks:
    $from_ticket_id = Xss::filter($from_ticket_id);
    $from_task = Task::loadFromTicketId($from_ticket_id);
    if ($from_task === FALSE) {
      return new JsonResponse([
        'from_ticket_id' => $from_ticket_id,
        'message' => 'Task does not exist',
        'success' => 0,
        'method' => 'POST',
      ]);
    }

    $to_ticket_id = Xss::filter($to_ticket_id);
    $to_task = Task::loadFromTicketId($to_ticket_id);
    if ($to_task === FALSE) {
      return new JsonResponse([
        'to_ticket_id' => $to_ticket_id,
        'message' => 'Task does not exist',
        'success' => 0,
        'method' => 'POST',
      ]);
    }

    $from_task_id = $from_task->id();
    $to_task_id = $to_task->id();

    // Filter $type.
    $filtered_type = Xss::filter($type);

    // Check that $type is valid.
    if (Task::relationshipTypeExists($filtered_type) === FALSE) {
      return new JsonResponse([
        'message' => 'Invalid relationship type',
        'success' => 0,
        'method' => 'POST',
      ]);
    }

    // Check if there is a relationship between the two tasks.
    // We have to test both tasks, as it could reside on either.
    if ($from_task->checkIfRelationshipExists($to_task_id) !== FALSE ||
      $to_task->checkIfRelationshipExists($from_task_id) !== FALSE) {
      return new JsonResponse([
        'message' => 'There is already a relationship between these tickets',
        'success' => 0,
        'method' => 'POST',
      ]);
    }

    // Everything is okay; add the relationship.
    $from_task
      ->addRelationship($to_task_id, $filtered_type)
      ->save();

    // Return "ok".
    return new JsonResponse([
      'success' => 1,
      'method' => 'POST',
    ]);
  }

  /**
   * Remove a relationship between two tasks.
   *
   * @param string $from_ticket_id
   *   The Task ID where the relationship is stored.
   * @param string $to_ticket_id
   *   The Task ID that the relationship relates to.
   */
  public function removeRelationship(string $from_ticket_id, string $to_ticket_id) {
    // Load the tasks:
    $from_task = Task::loadFromTicketId($from_ticket_id);
    if ($from_task === FALSE) {
      return new JsonResponse([
        'message' => 'Task does not exist',
        'success' => 0,
        'method' => 'POST',
      ]);
    }

    $to_task = Task::loadFromTicketId($to_ticket_id);
    if ($to_task === FALSE) {
      return new JsonResponse([
        'message' => 'Task does not exist',
        'success' => 0,
        'method' => 'POST',
      ]);
    }

    $from_task_id = $from_task->id();
    $to_task_id = $to_task->id();

    // We need to check both from/to tasks, as the relationship could
    // reside on either:
    if ($from_task->checkIfRelationshipExists($to_task_id) !== FALSE) {
      $from_task->removeRelationship($to_task_id);
    }
    elseif ($to_task->checkIfRelationshipExists($from_task_id) !== FALSE) {
      $to_task->removeRelationship($from_task_id);
    }
    else {
      // Relationship doesn't exist.
      return new JsonResponse([
        'from_task_id' => $from_task_id,
        'to_task_id' => $to_task_id,
        'message' => 'Relationship does not exist',
        'success' => 0,
        'method' => 'POST',
      ]);
    }

    // Success!
    return new JsonResponse([
      'success' => 1,
      'method' => 'POST',
    ]);
  }

  /**
   * Reopens a closed task.
   *
   * @param string $ticket_id
   *   The Task ID.
   */
  public function reopenTask(string $ticket_id) {
    // Load task.
    $task = Task::loadFromTicketId($ticket_id);
    if ($task === FALSE) {
      // Task doesn't exist; warn and redirect.
      $this->messenger->addStatus("Task does not exist.");
      $url = Url::fromUri('internal:/burndown');
      $response = new RedirectResponse($url->toString());
      $response->send();
    }

    // Check if task is actually closed.
    if ($task->getCompleted() == FALSE) {
      $this->messenger->addStatus("Task is not closed.");
      $url = Url::fromUri('internal:/burndown/task/' . $task->id() . '/edit');
      $response = new RedirectResponse($url->toString());
      $response->send();
    }

    // Get project.
    $project = $task->getProject();
    $shortcode = $project->getShortcode();

    // Get backlog.
    $backlog = Swimlane::getBacklogFor($shortcode);

    // Set task to open.
    $task
      ->setCompleted(FALSE)
      ->setSwimlane($backlog)
      ->setResolution('')
      ->save();

    // Redirect to backlog.
    $this->messenger->addStatus("Task has been reopened.");
    $url = Url::fromUri('internal:/burndown/backlog/' . $shortcode);
    $response = new RedirectResponse($url->toString());
    $response->send();
  }

  /**
   * Redirects directly to the task add form for a given project shortcode.
   *
   * @param string $shortcode
   *   The project shortcode.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect to the task add form with shortcode and destination.
   */
  public function addTaskRedirect($shortcode) {
    $destination = '/burndown/backlog/' . $shortcode;
    $url = Url::fromUri('base:/burndown/task/add/task', [
      'absolute' => TRUE,
      'query' => [
        'shortcode' => $shortcode,
        'destination' => $destination,
      ],
    ]);
    return new RedirectResponse($url->toString());
  }

  /**
   * Callback for task bundle add route.
   */
  public function addBundleSelect($shortcode) {
    // Get list of bundles.
    $bundles = $this->entityTypeBundleInfo->getBundleInfo('burndown_task');

    $destination = '/burndown/backlog/' . $shortcode;

    $links = [];

    // Create links to add pages.
    foreach ($bundles as $bundle_id => $bundle) {
      $add_link = '/burndown/task/add/' . $bundle_id;
      $task_type = ucfirst($bundle['label']);
      $link_text = $this->t('Add a @type Task', ['@type' => $task_type]);
      $links[] = Link::fromTextAndUrl($link_text, Url::fromUri('base:' . $add_link, [
        'absolute' => TRUE,
        'query' => [
          'shortcode' => $shortcode,
          'destination' => $destination,
        ],
      ]));
    }

    // Return markup.
    return [
      '#theme' => 'burndown_multi_bundle_add',
      '#links' => $links,
    ];
  }

  /**
   * Displays a Task revision.
   *
   * @param int $burndown_task_revision
   *   The Task revision ID.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  public function revisionShow($burndown_task_revision) {
    $burndown_task = $this->entityTypeManager()->getStorage('burndown_task')
      ->loadRevision($burndown_task_revision);
    $view_builder = $this->entityTypeManager()->getViewBuilder('burndown_task');

    return $view_builder->view($burndown_task);
  }

  /**
   * Page title callback for a Task revision.
   *
   * @param int $burndown_task_revision
   *   The Task revision ID.
   *
   * @return string
   *   The page title.
   */
  public function revisionPageTitle($burndown_task_revision) {
    $burndown_task = $this->entityTypeManager()->getStorage('burndown_task')
      ->loadRevision($burndown_task_revision);
    return $this->t('Revision of %title from %date', [
      '%title' => $burndown_task->label(),
      '%date' => $this->dateFormatter->format($burndown_task->getRevisionCreationTime()),
    ]);
  }

  /**
   * Generates an overview table of older revisions of a Task.
   *
   * @param \Drupal\burndown\Entity\TaskInterface $burndown_task
   *   A Task object.
   *
   * @return array
   *   An array as expected by drupal_render().
   */
  public function revisionOverview(TaskInterface $burndown_task) {
    $account = $this->currentUser();
    $burndown_task_storage = $this->entityTypeManager()->getStorage('burndown_task');

    $langcode = $burndown_task->language()->getId();
    $langname = $burndown_task->language()->getName();
    $languages = $burndown_task->getTranslationLanguages();
    $has_translations = (count($languages) > 1);
    $build['#title'] = $has_translations ? $this->t(
      '@langname revisions for %title', [
        '@langname' => $langname,
        '%title' => $burndown_task->label(),
      ]) : $this->t(
      'Revisions for %title', [
        '%title' => $burndown_task->label(),
      ]);

    $header = [$this->t('Revision'), $this->t('Operations')];
    $revert_permission = (($account->hasPermission("revert all task revisions") || $account->hasPermission('administer task entities')));
    $delete_permission = (($account->hasPermission("delete all task revisions") || $account->hasPermission('administer task entities')));

    $rows = [];

    $vids = $burndown_task_storage->revisionIds($burndown_task);

    $latest_revision = TRUE;

    foreach (array_reverse($vids) as $vid) {
      /** @var \Drupal\burndown\TaskInterface $revision */
      $revision = $burndown_task_storage->loadRevision($vid);
      // Only show revisions that are affected by the language that is being
      // displayed.
      if ($revision->hasTranslation($langcode)
        && $revision->getTranslation($langcode)->isRevisionTranslationAffected()
        ) {
        $username = [
          '#theme' => 'username',
          '#account' => $revision->getRevisionUser(),
        ];

        // Use revision link to link to revisions that are not active.
        $date = $this->dateFormatter->format($revision->getRevisionCreationTime(), 'short');
        if ($vid != $burndown_task->getRevisionId()) {
		  $link = Link::fromTextAndUrl($date, new Url('entity.burndown_task.revision', [
            'burndown_task' => $burndown_task->id(),
            'burndown_task_revision' => $vid,
           ]))->toString();
        }
        else {
          $link = $burndown_task->toLink($date)->toString();
        }

        $row = [];
        $column = [
          'data' => [
            '#type' => 'inline_template',
            '#template' => '{% trans %}{{ date }} by {{ username }}{% endtrans %}{% if message %}<p class="revision-log">{{ message }}</p>{% endif %}',
            '#context' => [
              'date' => $link,
              'username' => $this->renderer->renderPlain($username),
              'message' => [
                '#markup' => $revision->getRevisionLogMessage(),
                '#allowed_tags' => Xss::getHtmlTagList(),
              ],
            ],
          ],
        ];
        $row[] = $column;

        if ($latest_revision) {
          $row[] = [
            'data' => [
              '#prefix' => '<em>',
              '#markup' => $this->t('Current revision'),
              '#suffix' => '</em>',
            ],
          ];
          foreach ($row as &$current) {
            $current['class'] = ['revision-current'];
          }
          $latest_revision = FALSE;
        }
        else {
          $links = [];
          if ($revert_permission) {
            $links['revert'] = [
              'title' => $this->t('Revert'),
              'url' => $has_translations ?
              Url::fromRoute('entity.burndown_task.translation_revert', [
                'burndown_task' => $burndown_task->id(),
                'burndown_task_revision' => $vid,
                'langcode' => $langcode,
              ]) :
              Url::fromRoute('entity.burndown_task.revision_revert', [
                'burndown_task' => $burndown_task->id(),
                'burndown_task_revision' => $vid,
              ]),
            ];
          }

          if ($delete_permission) {
            $links['delete'] = [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute('entity.burndown_task.revision_delete', [
                'burndown_task' => $burndown_task->id(),
                'burndown_task_revision' => $vid,
              ]),
            ];
          }

          $row[] = [
            'data' => [
              '#type' => 'operations',
              '#links' => $links,
            ],
          ];
        }

        $rows[] = $row;
      }
    }

    $build['burndown_task_revisions_table'] = [
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
    ];

    return $build;
  }

}
