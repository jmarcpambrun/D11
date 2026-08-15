<?php

namespace Drupal\burndown\Controller;

use Drupal\burndown\Entity\ProjectInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ProjectController.
 *
 *  Returns responses for Project routes.
 */
class ProjectController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->renderer = $container->get('renderer');
    return $instance;
  }

  /**
   * Displays a Project revision.
   *
   * @param int $burndown_project_revision
   *   The Project revision ID.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  public function revisionShow($burndown_project_revision) {
    /** @var \Drupal\burndown\ProjectStorageInterface $project_storage */
    $project_storage = $this->entityTypeManager()->getStorage('burndown_project');
    /** @var \Drupal\burndown\Entity\Project $burndown_project */
    $burndown_project = $project_storage->loadRevision($burndown_project_revision);
    $view_builder = $this->entityTypeManager()->getViewBuilder('burndown_project');

    return $view_builder->view($burndown_project);
  }

  /**
   * Page title callback for a Project revision.
   *
   * @param int $burndown_project_revision
   *   The Project revision ID.
   *
   * @return string
   *   The page title.
   */
  public function revisionPageTitle($burndown_project_revision) {
    /** @var \Drupal\burndown\ProjectStorageInterface $project_storage */
    $project_storage = $this->entityTypeManager()->getStorage('burndown_project');
    /** @var \Drupal\burndown\Entity\Project $burndown_project */
    $burndown_project = $project_storage->loadRevision($burndown_project_revision);
    return $this->t('Revision of %title from %date', [
      '%title' => $burndown_project->label(),
      '%date' => $this->dateFormatter->format($burndown_project->getRevisionCreationTime()),
    ]);
  }

  /**
   * Generates an overview table of older revisions of a Project.
   *
   * @param \Drupal\burndown\Entity\ProjectInterface $burndown_project
   *   A Project object.
   *
   * @return array
   *   An array as expected by drupal_render().
   */
  public function revisionOverview(ProjectInterface $burndown_project) {
    $account = $this->currentUser();
    /** @var \Drupal\burndown\ProjectStorageInterface $burndown_project_storage */
    $burndown_project_storage = $this->entityTypeManager()->getStorage('burndown_project');

    $langcode = $burndown_project->language()->getId();
    $langname = $burndown_project->language()->getName();
    $languages = $burndown_project->getTranslationLanguages();
    $has_translations = (count($languages) > 1);
    $build['#title'] = $has_translations ? $this->t(
      '@langname revisions for %title',
      [
        '@langname' => $langname,
        '%title' => $burndown_project->label(),
      ]
      ) : $this->t(
        'Revisions for %title',
        [
          '%title' => $burndown_project->label(),
        ]
      );

    $header = [$this->t('Revision'), $this->t('Operations')];
    $revert_permission = (($account->hasPermission("revert all project revisions") || $account->hasPermission('administer project entities')));
    $delete_permission = (($account->hasPermission("delete all project revisions") || $account->hasPermission('administer project entities')));

    $rows = [];

    $vids = $burndown_project_storage->revisionIds($burndown_project);

    $latest_revision = TRUE;

    foreach (array_reverse($vids) as $vid) {
      /** @var \Drupal\burndown\Entity\ProjectInterface $revision */
      $revision = $burndown_project_storage->loadRevision($vid);
      // Only show revisions that are affected by the language that is being
      // displayed.
      if ($revision->hasTranslation($langcode) && $revision->getTranslation($langcode)->isRevisionTranslationAffected()) {
        $username = [
          '#theme' => 'username',
          '#account' => $revision->getRevisionUser(),
        ];

        // Use revision link to link to revisions that are not active.
        $date = $this->dateFormatter->format($revision->getRevisionCreationTime(), 'short');
        if ($vid != $burndown_project->getRevisionId()) {
          $link = Link::fromTextAndUrl($date, new Url('entity.burndown_project.revision', [
            'burndown_project' => $burndown_project->id(),
            'burndown_project_revision' => $vid,
          ]))->toString();
        }
        else {
          $link = $burndown_project->toLink($date)->toString();
        }

        $row = [];
        $column = [
          'data' => [
            '#type' => 'inline_template',
            '#template' => '{% trans %}{{ date }} by {{ username }}{% endtrans %}{% if message %}<p class="revision-log">{{ message }}</p>{% endif %}',
            '#context' => [
              'date' => $link,
              'username' => $this->renderer->renderInIsolation($username),
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
              Url::fromRoute('entity.burndown_project.translation_revert', [
                'burndown_project' => $burndown_project->id(),
                'burndown_project_revision' => $vid,
                'langcode' => $langcode,
              ]) :
              Url::fromRoute('entity.burndown_project.revision_revert', [
                'burndown_project' => $burndown_project->id(),
                'burndown_project_revision' => $vid,
              ]),
            ];
          }

          if ($delete_permission) {
            $links['delete'] = [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute('entity.burndown_project.revision_delete', [
                'burndown_project' => $burndown_project->id(),
                'burndown_project_revision' => $vid,
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

    $build['burndown_project_revisions_table'] = [
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
    ];

    return $build;
  }

}
