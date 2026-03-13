<?php

namespace Drupal\burndown\Controller;

use Drupal\burndown\Entity\SprintInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SprintController.
 *
 *  Returns responses for Sprint routes.
 */
class SprintController extends ControllerBase implements ContainerInjectionInterface {

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
   * @var {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->renderer = $container->get('renderer');
    return $instance;
  }

  /**
   * Displays a Sprint revision.
   *
   * @param int $burndown_sprint_revision
   *   The Sprint revision ID.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  public function revisionShow($burndown_sprint_revision) {
    $burndown_sprint = $this->entityTypeManager()->getStorage('burndown_sprint')
      ->loadRevision($burndown_sprint_revision);
    $view_builder = $this->entityTypeManager()->getViewBuilder('burndown_sprint');

    return $view_builder->view($burndown_sprint);
  }

  /**
   * Page title callback for a Sprint revision.
   *
   * @param int $burndown_sprint_revision
   *   The Sprint revision ID.
   *
   * @return string
   *   The page title.
   */
  public function revisionPageTitle($burndown_sprint_revision) {
    $burndown_sprint = $this->entityTypeManager()->getStorage('burndown_sprint')
      ->loadRevision($burndown_sprint_revision);
    return $this->t('Revision of %title from %date', [
      '%title' => $burndown_sprint->label(),
      '%date' => $this->dateFormatter->format($burndown_sprint->getRevisionCreationTime()),
    ]);
  }

  /**
   * Generates an overview table of older revisions of a Sprint.
   *
   * @param \Drupal\burndown\Entity\SprintInterface $burndown_sprint
   *   A Sprint object.
   *
   * @return array
   *   An array as expected by drupal_render().
   */
  public function revisionOverview(SprintInterface $burndown_sprint) {
    $account = $this->currentUser();
    $burndown_sprint_storage = $this->entityTypeManager()->getStorage('burndown_sprint');

    $langcode = $burndown_sprint->language()->getId();
    $langname = $burndown_sprint->language()->getName();
    $languages = $burndown_sprint->getTranslationLanguages();
    $has_translations = (count($languages) > 1);
    $build['#title'] = $has_translations ? $this->t('@langname revisions for %title', ['@langname' => $langname, '%title' => $burndown_sprint->label()]) : $this->t('Revisions for %title', ['%title' => $burndown_sprint->label()]);

    $header = [$this->t('Revision'), $this->t('Operations')];
    $revert_permission = (($account->hasPermission("revert all sprint revisions") || $account->hasPermission('administer sprint entities')));
    $delete_permission = (($account->hasPermission("delete all sprint revisions") || $account->hasPermission('administer sprint entities')));

    $rows = [];

    $vids = $burndown_sprint_storage->revisionIds($burndown_sprint);

    $latest_revision = TRUE;

    foreach (array_reverse($vids) as $vid) {
      /** @var \Drupal\burndown\SprintInterface $revision */
      $revision = $burndown_sprint_storage->loadRevision($vid);
      // Only show revisions that are affected by the language that is being
      // displayed.
      if ($revision->hasTranslation($langcode) && $revision->getTranslation($langcode)->isRevisionTranslationAffected()) {
        $username = [
          '#theme' => 'username',
          '#account' => $revision->getRevisionUser(),
        ];

        // Use revision link to link to revisions that are not active.
        $date = $this->dateFormatter->format($revision->getRevisionCreationTime(), 'short');
        if ($vid != $burndown_sprint->getRevisionId()) {
		  $link = Link::fromTextAndUrl($date, new Url('entity.burndown_sprint.revision', [
             'burndown_sprint' => $burndown_sprint->id(),
             'burndown_sprint_revision' => $vid,
           ]))->toString();
        }
        else {
          $link = $burndown_sprint->toLink($date)->toString();
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
              Url::fromRoute('entity.burndown_sprint.translation_revert', [
                'burndown_sprint' => $burndown_sprint->id(),
                'burndown_sprint_revision' => $vid,
                'langcode' => $langcode,
              ]) :
              Url::fromRoute('entity.burndown_sprint.revision_revert', [
                'burndown_sprint' => $burndown_sprint->id(),
                'burndown_sprint_revision' => $vid,
              ]),
            ];
          }

          if ($delete_permission) {
            $links['delete'] = [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute('entity.burndown_sprint.revision_delete', [
                'burndown_sprint' => $burndown_sprint->id(),
                'burndown_sprint_revision' => $vid,
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

    $build['burndown_sprint_revisions_table'] = [
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
    ];

    return $build;
  }

}
