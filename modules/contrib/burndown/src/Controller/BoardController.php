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
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller for the Burndown Board object.
 */
class BoardController extends ControllerBase implements ContainerInjectionInterface {

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
   * Constructs a BoardController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entityTypeManager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   A request stack.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
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
   * Allow access if have general board perm, or specific project view perm.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged-in user.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function checkAccess(AccountInterface $account): AccessResult {
    $shortcode = $this->requestStack->getCurrentRequest()->attributes->get('shortcode');
    $project = Project::loadFromShortcode($shortcode);
    $project_id = (!empty($project)) ? $project->id() : 'no_project';
    $allowed_perms = [
      'access burndown board',
      "{$project_id} view project",
    ];
    return AccessResult::allowedIfHasPermissions($account, $allowed_perms, 'OR');
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
      return $this->t('Board: %projectname', [
        '%projectname' => $projectName,
      ]);
    }

    return $this->t('Board');
  }

  /**
   * Callback for `burndown/board/{shortcode} route.
   */
  public function getBoard($shortcode) {
    // Redirect to project dashboard if no shortcode is provided.
    if (empty($shortcode)) {
      return new RedirectResponse('/burndown/project');
    }

    $data = [];

    // Sanitize input.
    $code = Html::escape($shortcode);

    // Get board type.
    $board_type = "kanban";
    $project = Project::loadFromShortcode($code);
    if ($project !== FALSE) {
      $board_type = $project->getBoardType();
    }

    // @todo Inject query.
    $assigned_to = $this->requestStack->getCurrentRequest()->query->get('assigned_to');
    if (!empty($assigned_to)) {
      $assigned_to = explode(',', $assigned_to);
    }

    // For sprint boards, we need to ensure that we only grab tasks
    // for a sprint that is current and open.
    $sprint_id = NULL;
    if ($board_type === 'sprint') {
      $sprint = Sprint::getCurrentSprintFor($code);
      if ($sprint === FALSE) {
        // We don't want to show any tasks on a sprint board where
        // there isn't a current sprint.
        $sprint_id = 0;
      }
      else {
        $sprint_id = $sprint->id();
      }
    }

    // Get swimlanes.
    $swimlanes = Swimlane::getBoardSwimlanes($code);

    // Unique users on the board.
    $users = [];

    // Get tasks for each column (swimlane).
    foreach ($swimlanes as $swimlane) {
      $swimlane_name = $swimlane->getName();
      $swimlane_id = $swimlane->id();
      $swimlane_tasks = [];

      if (is_null($sprint_id) || ($sprint_id > 0)) {
        $tasks = Task::getTasksForSwimlane($code, $swimlane_name, $sprint_id, $assigned_to);

        if (!empty($tasks)) {
          foreach ($tasks as $task) {
            // Get user.
            $user_data = $task->getAssignedToData();
            $user_id = $user_data['id'];

            if (isset($user_id) &&
            !array_key_exists($user_id, $users) &&
            !empty($assigned_to)) {
              $user_data['active'] = in_array($user_id, $assigned_to) ? 'active' : '';
              $users[$user_id] = $user_data;
            }

            // Add a task for the swimlane.
            $swimlane_tasks[] = [
              '#theme' => 'burndown_task_card',
              '#data' => $task->getData(),
            ];
          }
        }
      }

      $data[] = [
        'swimlane_name' => $swimlane_name,
        'swimlane_id' => $swimlane_id,
        'tasks' => $swimlane_tasks,
      ];
    }

    $theme = [
      '#theme' => 'burndown_board',
      '#data' => [
        'project' => $code,
        'board_type' => $board_type,
        'swimlanes' => $data,
        'users' => $users,
      ],
      '#cache' => [
        'max-age' => 0,
      ],
    ];

    // We need to maintain Drupal 9 compatibility for now, so can't use
    // D10's access manager.
    if ($this->currentUser->hasPermission('reorder burndown backlog')) {
      $theme['#attached'] = [
        'library' => [
          'burndown/drupal.burndown.board',
        ],
      ];
    }
    // Version of the library that only has css for the board.
    else {
      $theme['#attached'] = [
        'library' => [
          'burndown/drupal.burndown.unsortable_board',
        ],
      ];
    }

    return $theme;
  }

  /**
   * Callback for `burndown/api/backlog/change_swimlane` API method.
   */
  public function changeSwimlane(Request $request) {
    // Get request data.
    $task_id = $request->request->get('task_id');
    $from_swimlane_id = $request->request->get('from_swimlane');
    $to_swimlane_id = $request->request->get('to_swimlane');

    // Sanitize input.
    $task_id = Html::escape($task_id);
    $from_swimlane_id = Html::escape($from_swimlane_id);
    $to_swimlane_id = Html::escape($to_swimlane_id);

    // Load entities.
    $task = Task::loadFromTicketId($task_id);
    $from_swimlane = Swimlane::load($from_swimlane_id);
    $to_swimlane = Swimlane::load($to_swimlane_id);

    // Validate that entities exist.
    if ($task === FALSE ||
      $from_swimlane === FALSE ||
      $to_swimlane === FALSE) {
      return new JsonResponse([
        'success' => 0,
        'message' => 'Entities do not exist.',
        'method' => 'POST',
      ]);
    }

    // Validate that entities are in same project.
    $task_project = $task->getProject();
    $from_swimlane_project = $from_swimlane->getProject();
    $to_swimlane_project = $to_swimlane->getProject();
    if ($task_project->id() !== $from_swimlane_project->id() ||
      $task_project->id() !== $to_swimlane_project->id()) {
      return new JsonResponse([
        'success' => 0,
        'message' => 'Entities are not in the same project.',
        'method' => 'POST',
      ]);
    }

    // Validate that task is currently in from_swimlane.
    if ($task->getSwimlane()->id() !== $from_swimlane->id()) {
      return new JsonResponse([
        'success' => 0,
        'message' => 'Task was not in the "from" column.',
        'method' => 'POST',
      ]);
    }

    // Update column (swimlane).
    $task
      ->setSwimlane($to_swimlane)
      ->save();

    // Return JSON response.
    return new JsonResponse([
      'success' => 1,
      'method' => 'POST',
    ]);
  }

  /**
   * Callback for `burndown/api/board_reorder` API method.
   */
  public function reorderBoard(Request $request) {
    // Get our new sort order.
    $sort = $request->request->all('sort');

    if (!empty($sort)) {
      foreach ($sort as $swimlane_id => $swimlane) {
        // Initialize counter.
        $counter = 0;

        if (!empty($swimlane)) {
          foreach ($swimlane as $ticket_id) {
            $task = Task::loadFromTicketId($ticket_id);
            if ($task !== FALSE) {
              $task
                ->setBoardSort($counter)
                ->save();

              $counter++;
            }
          }
        }
      }
    }

    // Return JSON response.
    return new JsonResponse([
      'success' => 1,
      'sort' => $sort,
      'method' => 'POST',
    ]);
  }

  /**
   * Callback for `burndown/api/board/send_to_backlog` API method.
   */
  public function sendToBacklog($ticket_id) {
    // Sanitize input.
    $id = Html::escape($ticket_id);

    $task = Task::loadFromTicketId($id);

    if ($task !== FALSE) {
      $project = $task->getProject();
      $shortcode = $project->getShortcode();
      $backlog = Swimlane::getBacklogFor($shortcode);
      if ($backlog !== FALSE) {
        $task
          ->setSwimlane($backlog)
          ->set('sprint', NULL)
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

}
