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
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller for the Burndown module's Completed task listing.
 */
class CompletedController extends ControllerBase implements ContainerInjectionInterface {
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
   * Constructs a CompletedController object.
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
      return $this->t('Completed Tasks: %projectname', [
        '%projectname' => $projectName,
      ]);
    }

    return $this->t('Completed Tasks');
  }

  /**
   * Allow access if have general completed perm, or specific project view perm.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged-in user.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function checkAccess(AccountInterface $account): AccessResult {
    $shortcode = $this->requestStack->getCurrentRequest()->attributes->get('shortcode');
    $project = Project::loadFromShortcode($shortcode);
    $project_id = (!empty($project)) ? $project->id() : 'no_project';
    $allowed_perms = [
      'access completed board',
      "{$project_id} view project",
    ];
    return AccessResult::allowedIfHasPermissions($account, $allowed_perms, 'OR');
  }

  /**
   * Callback for `burndown/completed/{shortcode} route.
   */
  public function getCompleted($shortcode) {
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

    // Kanban boards.
    if ($board_type == 'kanban') {
      $data = [];
      $tasks = [];

      $completed_lanes = Swimlane::getCompletedSwimlanes($shortcode);
      if ($completed_lanes !== FALSE) {
        foreach ($completed_lanes as $lane) {
          // Get tasks for lane.
          $lane_tasks = Task::getTasksForSwimlane($code, $lane->getName());

          // Check if tasks are actually completed.
		  if(!empty($lane_tasks)) {
            foreach ($lane_tasks as $lane_task) {
              if ($lane_task->isCompleted()) {
                $tasks[] = $lane_task;
              }
            }
		  }
        }
      }

      // Iterate through backlog tasks to build dataset.
      if (!empty($tasks)) {
        $data[] = $viewBuilder->viewMultiple($tasks, 'teaser');
      }

      // Return data.
      return [
        '#theme' => 'burndown_completed_kanban',
        '#data' => [
          'project' => $code,
          'board_type' => $board_type,
          'tasks' => $data,
        ],
        '#attached' => [
          'library' => [
            'burndown/drupal.burndown.completed',
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
      $pre_sprint_tasks = [];

      // Get closed sprints.
      $completed_sprints = Sprint::getCompletedSprintsFor($code);
      if ($completed_sprints !== FALSE) {
        foreach ($completed_sprints as $sprint) {
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

      // We also need to get any tasks that were closed prior to
      // being assigned to a sprint.
      $completed_lanes = Swimlane::getCompletedSwimlanes($shortcode);
      if ($completed_lanes !== FALSE) {
        foreach ($completed_lanes as $lane) {
          // Get tasks for lane.
          $lane_tasks = Task::getClosedPreSprintTasks($code, $lane->getName());

          // Check if tasks are actually completed.
          foreach ($lane_tasks as $lane_task) {
            if ($lane_task->isCompleted()) {
              $pre_sprint_tasks[] = $lane_task;
            }
          }
        }
      }
      if (!empty($pre_sprint_tasks)) {
        $pre_sprint_tasks = $viewBuilder->viewMultiple($pre_sprint_tasks, 'teaser');
      }

      // Return data.
      return [
        '#theme' => 'burndown_completed_sprint',
        '#data' => [
          'project' => $code,
          'board_type' => $board_type,
          'sprints' => $rendered_sprints,
          'sprint_tasks' => $sprint_tasks,
          'pre_sprint_tasks' => $pre_sprint_tasks,
        ],
        '#attached' => [
          'library' => [
            'burndown/drupal.burndown.completed',
          ],
        ],
        '#cache' => [
          'max-age' => 0,
        ],
      ];
    }
  }

}
