<?php

namespace Drupal\burndown\Plugin\Block;

use Drupal\burndown\Entity\Project;
use Drupal\burndown\Entity\Task;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Link;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a project sidebar nav block.
 *
 * @Block(
 *   id = "burndown_project_nav_block",
 *   admin_label = @Translation("Project navigation block"),
 *   category = "Burndown",
 * )
 */
class ProjectNavBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  /**
   * Path service.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $pathService;

  /**
   * Route match.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * Construct a new ProjectCloudBlock object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccountProxyInterface $account, CurrentPathStack $pathService, CurrentRouteMatch $routeMatch) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->account = $account;
    $this->pathService = $pathService;
    $this->routeMatch = $routeMatch;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('path.current'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $boards = [];
    $links = [];

    // Get current user.
    $user = $this->account;

    // Dashboard.
    if ($user->hasPermission('access burndown')) {
      $links[] = Link::fromTextAndUrl('Burndown Dashboard', Url::fromUri('base:burndown/', [
        'absolute' => TRUE,
      ]));
    }

    // Add a project.
    if ($user->hasPermission('add project entities')) {
      $links[] = Link::fromTextAndUrl('Add a Project', Url::fromUri('base:burndown/project/add', [
        'absolute' => TRUE,
      ]));
    }

    if ($shortcode = $this->routeMatch->getParameter('shortcode')) {
      // Boards for the project.
      $boards[] = Link::fromTextAndUrl('Backlog', Url::fromRoute('burndown.backlog', ['shortcode' => $shortcode], ['absolute' => TRUE]));
      $boards[] = Link::fromTextAndUrl('Project Board', Url::fromRoute('burndown.board', ['shortcode' => $shortcode], ['absolute' => TRUE]));
      $boards[] = Link::fromTextAndUrl('Completed Tasks', Url::fromRoute('burndown.completed', ['shortcode' => $shortcode], ['absolute' => TRUE]));

      // Return destination:
      $current_board = $this->getBoard();
      $destination = base_path() . 'burndown/' . $current_board . '/' . $shortcode;

      // Load the project.
      $project = Project::loadFromShortcode($shortcode);
      $project_id = $project->id();

      // Useful Links.
      // If user can edit the project:
      if ($user->hasPermission('edit project entities')) {
        $links[] = Link::fromTextAndUrl('Edit the Project', Url::fromUri('base:burndown/project/' . $project_id . '/edit', [
          'absolute' => TRUE,
          'query' => [
            'destination' => $destination,
          ],
        ]));
      }

      // If user can add tasks:
      if ($user->hasPermission('add task entities')) {
        if (Task::numberOfTaskTypes() == 1) {
          $add_link = 'burndown/task/add/task';

          $links[] = Link::fromTextAndUrl('Add a Task', Url::fromUri('base:' . $add_link, [
            'absolute' => TRUE,
            'query' => [
              'shortcode' => $shortcode,
              'destination' => $destination,
            ],
          ]));
        }
        else {
          $add_link = '/burndown/task_add_multi_bundle/' . $shortcode;

          $links[] = Link::fromTextAndUrl('Add a Task', Url::fromUri('base:' . $add_link, [
            'absolute' => TRUE,
            'query' => [
              'destination' => $destination,
            ],
          ]));
        }
      }

      // Check if project is a sprint project.
      if ($project->isSprint() && $user->hasPermission('add sprint entities')) {
        // http://burndown.local/burndown/sprint/add?shortcode=NEW&destination=/burndown/backlog/NEW
        $links[] = Link::fromTextAndUrl('Add a Sprint', Url::fromUri('base:burndown/sprint/add', [
          'absolute' => TRUE,
          'query' => [
            'shortcode' => $shortcode,
            'destination' => $destination,
          ],
        ]));
      }
    }

    return [
      '#theme' => 'burndown_sidebar_nav',
      '#shortcode' => $shortcode,
      '#boards' => $boards,
      '#links' => $links,
      '#attached' => [
        'library' => [
          'burndown/drupal.burndown.sidebar_nav',
        ],
      ],
    ];
  }

  /**
   * If we are on a particular board, return the type.
   */
  public function getBoard() {
    $current_path = $this->pathService->getPath();

    if (strpos($current_path, 'board') !== FALSE) {
      $board = 'board';
    }
    elseif (strpos($current_path, 'completed') !== FALSE) {
      $board = 'completed';
    }
    else {
      $board = 'backlog';
    }

    return $board;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    if ($project = $this->routeMatch->getParameter('shortcode')) {
      return Cache::mergeTags(parent::getCacheTags(), ['project:' . $project]);
    }
    else {
      return parent::getCacheTags();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }

}
