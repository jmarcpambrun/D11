<?php

namespace Drupal\burndown\Plugin\Block;

use Drupal\burndown\Services\ProjectCloudService;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a project "cloud" block.
 *
 * @Block(
 *   id = "burndown_project_cloud_block",
 *   admin_label = @Translation("Project cloud block"),
 *   category = "Burndown",
 * )
 */
class ProjectCloudBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * A service for generating a project word cloud.
   *
   * @var \Drupal\burndown\Services\ProjectCloudService
   */
  protected $projectCloudService;

  /**
   * A service for getting the current path.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $pathService;

  /**
   * Construct a new ProjectCloudBlock object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ProjectCloudService $projectCloudService, CurrentPathStack $pathService) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->projectCloudService = $projectCloudService;
    $this->pathService = $pathService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('burndown_service.project_cloud'),
      $container->get('path.current')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Return our build array.
    return [
      '#theme' => 'burndown_project_cloud',
      '#data' => $this
        ->projectCloudService
        ->getProjectCloud(),
      '#board' => $this->getBoard(),
      '#attached' => [
        'library' => [
          'burndown/drupal.burndown.project_cloud',
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
    return Cache::mergeTags(
      parent::getCacheTags(),
      [
        'board:' . $this->getBoard(),
      ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }

}
