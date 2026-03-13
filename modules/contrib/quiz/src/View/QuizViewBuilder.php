<?php

namespace Drupal\quiz\View;

use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\Registry;
use Drupal\quiz\Entity\Quiz;
use Drupal\quiz\Util\QuizUtil;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * View builder for quiz.
 */
class QuizViewBuilder extends EntityViewBuilder {

  use MessengerTrait;

  /**
   * Constructs a new EntityViewBuilder.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Theme\Registry $theme_registry
   *   The theme registry.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The currently active route match object.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter service.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityRepositoryInterface $entity_repository,
    LanguageManagerInterface $language_manager,
    Registry $theme_registry,
    EntityDisplayRepositoryInterface $entity_display_repository,
    protected RouteMatchInterface $routeMatch,
    protected DateFormatterInterface $dateFormatter,
  ) {
    parent::__construct($entity_type, $entity_repository, $language_manager, $theme_registry, $entity_display_repository);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    $view_builder = new static(
      $entity_type,
      $container->get('entity.repository'),
      $container->get('language_manager'),
      $container->get('theme.registry'),
      $container->get('entity_display.repository'),
      $container->get('current_route_match'),
      $container->get('date.formatter')
    );
    $view_builder->setMessenger($container->get('messenger'));
    return $view_builder;
  }

  /**
   * {@inheritdoc}
   */
  public function buildComponents(array &$build, array $entities, array $displays, $view_mode): void {
    parent::buildComponents($build, $entities, $displays, $view_mode);

    /** @var \Drupal\quiz\Entity\Quiz $entity */
    foreach ($entities as $id => $entity) {
      $bundle = $entity->bundle();
      $display = $displays[$bundle];

      $build[$id]['quiz_config_overview'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['quiz-config-overview'],
        ],
      ];

      if ($display->getComponent('stats')) {
        $build[$id]['quiz_config_overview']['stats'] = $this->buildStatsComponent($entity);
      }

      if ($display->getComponent('take')) {
        $build[$id]['quiz_config_overview']['take'] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['quiz-config-overview-take'],
          ],
        ];
        $build[$id]['quiz_config_overview']['take']['content'] = $this->buildTakeComponent($entity);
      }
    }
  }

  /**
   * Helper function to build stats.
   *
   * @param \Drupal\quiz\Entity\quiz $quiz
   *   Quiz to pull stats from.
   *
   * @return array
   *   Array formed to hold "Stats" component.
   */
  protected function buildStatsComponent(Quiz $quiz): array {
    $stats = [
      [
        ['header' => TRUE, 'width' => '25%', 'data' => $this->t('Questions')],
        $quiz->getNumberOfQuestions(),
      ],
    ];

    if ($quiz->get('show_attempt_stats')->value) {
      $takes = $quiz->get('takes')->value == 0 ? $this->t('Unlimited') : $quiz->get('takes')->value;
      $stats[] = [
        ['header' => TRUE, 'data' => $this->t('Attempts allowed')],
        $takes,
      ];
    }

    if ($quiz->get('quiz_date')->isEmpty()) {
      $stats[] = [
        ['header' => TRUE, 'data' => $this->t('Available')],
        $this->t('Always'),
      ];
    }
    else {
      $quiz_date = $quiz->get('quiz_date');
      $stats[] = [
        ['header' => TRUE, 'data' => $this->t('Opens')],
        $this->dateFormatter->format($quiz_date->start_date->getTimestamp()),
      ];
      $stats[] = [
        ['header' => TRUE, 'data' => $this->t('Closes')],
        $this->dateFormatter->format($quiz_date->end_date->getTimestamp()),
      ];
    }

    if (!$quiz->get('pass_rate')->isEmpty()) {
      $stats[] = [
        ['header' => TRUE, 'data' => $this->t('Grade required to pass')],
        $quiz->get('pass_rate')->value . ' %',
      ];
    }

    if (!$quiz->get('time_limit')->isEmpty()) {
      $stats[] = [
        ['header' => TRUE, 'data' => $this->t('Time limit')],
        _quiz_format_duration($quiz->get('time_limit')->value),
      ];
    }

    $stats[] = [
      ['header' => TRUE, 'data' => $this->t('Backwards navigation')],
      $quiz->get('backwards_navigation') ? $this->t('Allowed') : $this->t('Forbidden'),
    ];

    return [
      '#attributes' => ['class' => ['quiz-config-overview-table']],
      '#theme' => 'table__quiz_stats',
      '#rows' => $stats,
    ];
  }

  /**
   * Helper function to build take attempts.
   *
   * @param \Drupal\quiz\Entity\quiz $quiz
   *   Quiz to pull take attempts from.
   *
   * @return array
   *   Array formed to hold "Take" component.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function buildTakeComponent(Quiz $quiz): array {
    $build = [];

    $access = $quiz->access('take', NULL, TRUE);
    // Check the permission before displaying start button.
    if (!$access->isForbidden()) {
      if (is_a($access, AccessResultReasonInterface::class)) {
        // There's a friendly success message available. Only display if we are
        // viewing the quiz.
        // @todo does not work because we cannot pass allowed reason, only
        // forbidden reason. The message is displayed in quiz_quiz_access().
        if ($this->routeMatch == 'entity.quiz.canonical') {
          $this->messenger->addMessage($access->getReason());
        }
      }

      $build['link'] = $quiz->toLink($this->t('Start @quiz', ['@quiz' => QuizUtil::getQuizName()]), 'take', [
        'language' => $this->languageManager->getCurrentLanguage(),
        'attributes' => [
          'class' => [
            'quiz-start-link',
            'button',
          ],
        ],
      ])->toRenderable();
    }
    // Only display a message when there is a reason available.
    elseif ($access instanceof AccessResultReasonInterface && $access->getReason()) {
      $build['message'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'quiz-not-available',
          ],
        ],
        '#markup' => $access->getReason(),
      ];
    }

    CacheableMetadata::createFromObject($access)
      ->setCacheMaxAge(0)
      ->applyTo($build);

    return $build;
  }

}
