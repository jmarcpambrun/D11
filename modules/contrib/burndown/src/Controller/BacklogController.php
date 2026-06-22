<?php

namespace Drupal\burndown\Controller;

use Drupal\burndown\Entity\Project;
use Drupal\burndown\Entity\Sprint;
use Drupal\burndown\Entity\Swimlane;
use Drupal\burndown\Entity\Task;
use Drupal\Component\Utility\Html;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller object for the Burndown Backlog.
 */
class BacklogController extends ControllerBase implements ContainerInjectionInterface {
  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a BacklogController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entityTypeManager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   A request stack.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, RequestStack $request_stack, AccountInterface $currentUser) {
    $this->entityTypeManager = $entityTypeManager;
    $this->requestStack = $request_stack;
    $this->currentUser = $currentUser;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
      $container->get('current_user')
    );
  }

  /**
   * Callback for page title.
   */
  public function getPageTitle($shortcode) {
    // Sanitize input.
    $code = Html::escape($shortcode);

    $project = Project::loadFromShortcode($code);
    if ($project !== FALSE) {
      $projectName = $project->getName();
      return $this->t('Backlog: %projectname', [
        '%projectname' => $projectName,
      ]);
    }

    return $this->t('Backlog');
  }

  /**
   * Allow access if have general backlog perm, or specific project view perm.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged-in user.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function checkAccess(AccountInterface $account): AccessResult {
    $project_id = $this->getProjectId();
    $allowed_perms = [
      'access burndown backlog',
      "{$project_id} view project",
    ];
    return AccessResult::allowedIfHasPermissions($account, $allowed_perms, 'OR');
  }

  /**
   * Callback for `burndown/backlog/{shortcode} route.
   */
  public function getBacklog($shortcode) {
    // Sanitize input.
    $code = Html::escape($shortcode);

    // View builder for tasks.
    $viewBuilder = $this->entityTypeManager
      ->getViewBuilder('burndown_task');

    // View builder for sprints.
    $viewBuilder2 = $this->entityTypeManager
      ->getViewBuilder('burndown_sprint');

    // Get board type.
    $board_type = "kanban";
    $project = Project::loadFromShortcode($code);
    if ($project !== FALSE) {
      $board_type = $project->getBoardType();
    }

    // Get return destination.
    $destination = '/burndown/backlog/' . $code;

    $project_id = $this->getProjectId();
    $add_task_perms = [
      'add task entities',
      "{$project_id} create entities"
    ];
    $access = AccessResult::allowedIfHasPermissions($this->currentUser, $add_task_perms, 'OR');
    $add_link = [];
    if ($access->isAllowed()) {
      // Determine what the link to add a new task should be (when there
      // are multiple task bundles, we don't need to specify which one).
      if (Task::numberOfTaskTypes() == 1) {
        $add_link = '/burndown/task/add/task';
        $add_link = Link::fromTextAndUrl(
        $this->t('Add a Task'),
        Url::fromUri('base:' . $add_link, [
          'absolute' => TRUE,
          'query' => [
            'shortcode' => $code,
            'destination' => $destination,
          ],
        ]));
      }
      else {
        $add_link = '/burndown/task_add_multi_bundle/' . $shortcode;
        $add_link = Link::fromTextAndUrl(
        $this->t('Add a Task'),
        Url::fromUri('base:' . $add_link, [
          'absolute' => TRUE,
          'query' => [
            'destination' => $destination,
          ],
        ]));
      }
      $add_link = $add_link->toRenderable();
      $add_link['#attributes']['class'] = 'button button-action';
    }

    // Kanban boards.
    if ($board_type == 'kanban') {
      $data = [];

      // Get backlog tasks for the project.
      $tasks = Task::getBacklogTasks($code);

      // Iterate through backlog tasks to build dataset.
      if (!empty($tasks)) {
        $data[] = $viewBuilder->viewMultiple($tasks, 'teaser');
      }

      // Return data.
      return [
        '#theme' => 'burndown_backlog_kanban',
        '#data' => [
          'project' => $code,
          'board_type' => $board_type,
          'tasks' => $data,
          'add_link' => $add_link,
        ],
        '#attached' => [
          'library' => [
            'burndown/drupal.burndown.backlog',
          ],
        ],
        '#cache' => [
          'max-age' => 0,
        ],
      ];
    }
    // Sprint boards.
    else {
      $sprints = [];
      $sprint_tasks = [];
      $rendered_sprints = [];

      // Get current opened sprint.
      $sprint = Sprint::getCurrentSprintFor($code);
      if ($sprint !== FALSE) {
        $sprints[$sprint->id()] = $sprint;
      }

      // Get unopened sprints.
      $backlog_sprints = Sprint::getBacklogSprintsFor($code);
      if ($backlog_sprints !== FALSE) {
        foreach ($backlog_sprints as $sprint) {
          $sprints[$sprint->id()] = $sprint;
        }
      }

      if (!empty($sprints)) {
        // Get tasks for the sprint.
        foreach ($sprints as $sprint) {
          $tasks = Task::getTasksForBacklogSprint($code, $sprint->id());
          if (!empty($tasks)) {
            $sprint_tasks[$sprint->id()] = $viewBuilder->viewMultiple($tasks, 'teaser');
          }

          // Build view for sprints.
          $rendered_sprints[$sprint->id()] = $viewBuilder2->view($sprint, 'full');
        }
      }

      // Get tasks that aren't in a sprint.
      $tasks = [];
      $backlog_tasks = Task::getBacklogTasks($code, TRUE);
      if (!empty($backlog_tasks)) {
        $tasks[] = $viewBuilder->viewMultiple($backlog_tasks, 'teaser');
      }

      // Return data.
      return [
        '#theme' => 'burndown_backlog_sprint',
        '#data' => [
          'project' => $code,
          'board_type' => $board_type,
          'sprints' => $rendered_sprints,
          'sprint_tasks' => $sprint_tasks,
          'tasks' => $tasks,
          'add_link' => $add_link,
        ],
        '#attached' => [
          'library' => [
            'burndown/drupal.burndown.backlog',
          ],
        ],
        '#cache' => [
          'max-age' => 0,
        ],
      ];
    }
  }

  /**
   * Callback for `burndown/api/backlog_reorder` API method.
   */
  public function reorderBacklog(Request $request) {
    // Get our new sort order.
    $sort = $request->request->all('sort');

    // Initialize counter.
    $counter = 0;

    if (!empty($sort)) {
      foreach ($sort as $ticket_id) {
        $task = Task::loadFromTicketId($ticket_id);
        if ($task !== FALSE) {
          $task
            ->setBacklogSort($counter)
            ->save();

          $counter++;
        }
      }
    }

    // Return JSON response.
    return new JsonResponse([
      'counter' => $counter,
      'success' => 1,
      'method' => 'POST',
    ]);
  }

  /**
   * Callback for `burndown/api/backlog/send_to_board` API method.
   */
  public function sendToBoard($ticket_id) {
    // Sanitize input.
    $id = Html::escape($ticket_id);

    $task = Task::loadFromTicketId($id);

    if ($task !== FALSE) {
      $project = $task->getProject();
      $shortcode = $project->getShortcode();
      $todo = Swimlane::getTodoSwimlane($shortcode);
      if ($todo !== FALSE) {
        $task
          ->setSwimlane($todo)
          ->save();

        // Return JSON response.
        return new JsonResponse([
          'success' => 1,
          'method' => 'POST',
        ]);
      }
    }

    // Return "error" JSON response.
    return new JsonResponse([
      'success' => 0,
      'method' => 'POST',
    ]);
  }

  /**
   * Callback for `burndown/api/backlog/change_sprint` API method.
   */
  public function changeSprint(Request $request) {
    // Get request data.
    $task_id = $request->request->get('task_id');
    $from_sprint_id = $request->request->get('from_sprint');
    $to_sprint_id = $request->request->get('to_sprint');

    // Sanitize input.
    $task_id = Html::escape($task_id);
    $from_sprint_id = Html::escape($from_sprint_id);
    $to_sprint_id = Html::escape($to_sprint_id);

    // Load entities.
    $task = Task::loadFromTicketId($task_id);
    $task_project = $task->getProject();
    if ($from_sprint_id !== 0) {
      $from_sprint = Sprint::load($from_sprint_id);
    }
    if ($to_sprint_id !== 0) {
      $to_sprint = Sprint::load($to_sprint_id);
    }

    // Validate that entities exist.
    if ($task === FALSE ||
      $from_sprint === FALSE ||
      $to_sprint === FALSE) {
      return new JsonResponse([
        'success' => 0,
        'message' => 'Entities do not exist.',
        'method' => 'POST',
      ]);
    }

    // Validate that entities are in same project.
    if (isset($from_sprint) && isset($to_sprint)) {
      $from_sprint_project = $from_sprint->getProject();
      $to_sprint_project = $to_sprint->getProject();
      if ($task_project->id() !== $from_sprint_project->id() ||
        $task_project->id() !== $to_sprint_project->id()) {
        return new JsonResponse([
          'success' => 0,
          'message' => 'Entities are not in the same project.',
          'method' => 'POST',
        ]);
      }
    }

    // Update sprint.
    if (isset($to_sprint)) {
      // Set the sprint.
      $task->setSprint($to_sprint);

      // If the sprint is open, we also need to set swimlane.
      if ($to_sprint->getStatus() == 'started') {
        $shortcode = $task_project->getShortcode();
        $todo = Swimlane::getTodoSwimlane($shortcode);
        if ($todo !== FALSE) {
          $task->setSwimlane($todo);
        }
      }

      // Save the task.
      $task->save();

      $response = "Setting sprint to " . $to_sprint->id();
    }
    // Otherwise it's going back in the backlog.
    else {
      $shortcode = $task_project->getShortcode();
      $backlog = Swimlane::getBacklogFor($shortcode);

      $task
        ->set('sprint', NULL)
        ->setSwimlane($backlog)
        ->save();

      $response = "Sending task to backlog.";
    }

    // Return JSON response.
    return new JsonResponse([
      'success' => 1,
      'response' => $response,
      'method' => 'POST',
    ]);
  }

  /**
   * Callback for `burndown/api/backlog/sprint_status/{shortcode}` API method.
   */
  public function sprintStatus($shortcode) {
    // Sanitize input.
    $code = Html::escape($shortcode);

    $sprints = [];
    $data = [];

    // Get current opened sprint.
    $current_sprint = Sprint::getCurrentSprintFor($code);
    if ($current_sprint !== FALSE) {
      $current_sprint_id = $current_sprint->id();
      $sprints[$current_sprint_id] = $current_sprint;
    }
    else {
      $current_sprint_id = 0;
    }

    // Get unopened sprints.
    $backlog_sprints = Sprint::getBacklogSprintsFor($code);
    if ($backlog_sprints !== FALSE) {
      foreach ($backlog_sprints as $sprint) {
        $sprints[$sprint->id()] = $sprint;
      }
    }

    if (!empty($sprints)) {
      foreach ($sprints as $sprint) {
        $is_current = ($sprint->id() == $current_sprint_id);

        $data[] = [
          'id' => $sprint->id(),
          'name' => $sprint->getName(),
          'status' => $sprint->getStatus(),
          'is_current' => $is_current ? '1' : '0',
          'can_open' => $sprint->canOpen() ? '1' : '0',
          'can_close' => (($sprint->getStatus() == 'started') && $is_current) ? '1' : '0',
        ];
      }
    }

    // Return sprint data.
    return new JsonResponse([
      'success' => 1,
      'data' => $data,
    ]);
  }

  /**
   * Callback for `burndown/api/backlog/open_sprint` API method.
   */
  public function openSprint(Request $request) {
    // Get request data.
    $sprint_id = $request->request->get('id');

    // Sanitize input.
    $sprint_id = intval($sprint_id);

    // Load sprint.
    $sprint = Sprint::load($sprint_id);
    if (is_null($sprint)) {
      return new JsonResponse([
        'success' => 0,
        'message' => 'Entity does not exist: ' . $sprint_id,
        'method' => 'POST',
      ]);
    }

    // Try to open the sprint.
    $success = $sprint->startSprint();
    if ($success) {
      return new JsonResponse([
        'success' => 1,
        'method' => 'POST',
      ]);
    }
    // See Sprint::startSprint for various reasons why a sprint can't open.
    else {
      return new JsonResponse([
        'success' => 0,
        'message' => 'Could not open sprint.',
        'method' => 'POST',
      ]);
    }
  }

  /**
   * Get the project ID for the current request.
   *
   * @return string
   *  The project ID which is a string integer, or 'no_project' if not found.
   */
  public function getProjectId(): string {
    $shortcode = $this->requestStack->getCurrentRequest()->attributes->get('shortcode');
    $project = Project::loadFromShortcode($shortcode);
    return (!empty($project)) ? $project->id() : 'no_project';
  }

}
