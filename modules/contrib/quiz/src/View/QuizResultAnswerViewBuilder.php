<?php

namespace Drupal\quiz\View;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Theme\Registry;
use Drupal\Core\Utility\Token;
use Drupal\quiz\Entity\QuizResultAnswer;
use Symfony\Component\DependencyInjection\ContainerInterface;

use function check_markup;

/**
 * Quiz Result Answer object view builder.
 */
class QuizResultAnswerViewBuilder extends EntityViewBuilder {

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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer service.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityRepositoryInterface $entity_repository,
    LanguageManagerInterface $language_manager,
    Registry $theme_registry,
    EntityDisplayRepositoryInterface $entity_display_repository,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Renderer $renderer,
    protected Token $token,
  ) {
    parent::__construct($entity_type, $entity_repository, $language_manager, $theme_registry, $entity_display_repository);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    return new static(
      $entity_type,
      $container->get('entity.repository'),
      $container->get('language_manager'),
      $container->get('theme.registry'),
      $container->get('entity_display.repository'),
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('token'),
    );
  }

  /**
   * Build the response content with feedback.
   *
   * @todo d8 putting this here, but needs to be somewhere else.
   */
  public function alterBuild(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, $view_mode) {
    assert($entity instanceof QuizResultAnswer);
    // Add the question display if configured.
    $view_modes = $this->entityDisplayRepository
      ->getViewModes('quiz_question');
    $view_builder = $this->entityTypeManager
      ->getViewBuilder('quiz_question');

    if ($entity->canReview("quiz_question_view_full")) {
      // Default view mode.
      $build["quiz_question_view_full"] = $view_builder->view($entity->getQuizQuestion());
    }
    foreach (array_keys($view_modes) as $view_mode) {
      // Custom view modes.
      if ($entity->canReview("quiz_question_view_" . $view_mode)) {
        $build["quiz_question_view_" . $view_mode] = $view_builder->view($entity->getQuizQuestion(), $view_mode);
      }
    }

    $class_name = (new \ReflectionClass($entity))->getShortName();

    $rows = [];

    $labels = [
      'attempt' => $this->t('Your answer'),
      'choice' => $this->t('Choice'),
      'correct' => $this->t('Correct?'),
      'score' => $this->t('Score'),
      'answer_feedback' => $this->t('Feedback'),
      'solution' => $this->t('Correct answer'),
    ];
    $this->moduleHandler()->alter('quiz_feedback_labels', $labels);

    foreach ($entity->getFeedbackValues() as $idx => $row) {
      foreach ($labels as $reviewType => $label) {
        if ((isset($row['data'][$reviewType]) && $entity->canReview($reviewType))) {
          // Add to table.
          $row_class = ['quiz-result-row'];
          foreach ($row['status'] as $key => $value) {
            if ($key == 'correct' && is_null($value)) {
              $row_class[] = 'quiz-result-null';
            }
            else {
              $row_class[] = $value ? "quiz-result-$key" : "quiz-result-not-$key";
            }
          }
          $rows[$idx]['class'] = $row_class;
          $rows[$idx]['data'][$reviewType] = [
            'data' => $row['data'][$reviewType],
            'class' => Html::cleanCssIdentifier("quiz-result-cell-$reviewType"),
          ];
          // Add to render.
          if ($display->getComponent($reviewType)) {
            $build[$reviewType] = [
              '#title' => $label,
              '#type' => 'item',
              '#markup' => $this->renderer->render($row['data'][$reviewType]),
            ];
          }
        }
      }
    }

    if ($entity->isEvaluated()) {
      $score = $entity->getPoints();
      if ($entity->isCorrect()) {
        $class = 'q-correct';
      }
      else {
        $class = 'q-wrong';
      }
    }
    else {
      $score = $this->t('?');
      $class = 'q-waiting';
    }

    $quiz_result = $entity->getQuizResult();

    if ($entity->canReview('score') || $quiz_result->access('update')) {
      $build['score']['#theme'] = 'quiz_question_score';
      $build['score']['#score'] = $score;
      $build['score']['#max_score'] = $entity->getMaxScore();
      $build['score']['#class'] = $class;
    }

    $table_classes = [
      'quiz-result-table',
      Html::cleanCssIdentifier($class_name),
    ];

    if ($rows) {
      $headers = array_intersect_key($labels, $rows[0]['data']);
      $build['table'] = [
        '#type' => 'table',
        '#header' => $headers,
        '#rows' => $rows,
        '#attributes' => [
          'class' => $table_classes,
        ],
      ];
    }

    if ($entity->canReview('question_feedback')) {
      $account = $quiz_result->getOwner();
      $token_data = [
        'global' => NULL,
        'quiz_question' => $entity->getQuizQuestion(),
        'user' => $account,
      ];
      $feedback = $this->token->replace($entity->getQuizQuestion()->get('feedback')->first()->getValue()['value'], $token_data);
      $build['question_feedback']['#markup'] = check_markup($feedback, $entity->getQuizQuestion()->get('feedback')->first()->getValue()['format']);
    }

    // Question feedback is dynamic.
    $build['#cache']['max-age'] = 0;

    return $build;
  }

}
