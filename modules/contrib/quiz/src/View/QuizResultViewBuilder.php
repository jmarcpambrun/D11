<?php

namespace Drupal\quiz\View;

use Drupal\Core\Config\Config;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Theme\Registry;
use Drupal\Core\Utility\Token;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\quiz\Entity\Quiz;
use Drupal\quiz\Entity\QuizResult;
use Drupal\quiz\Util\QuizUtil;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use function check_markup;

/**
 * Quiz Result object view builder.
 */
class QuizResultViewBuilder extends EntityViewBuilder {

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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user session account.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer service.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Drupal\Core\Config\Config $config
   *   The configuration settings for quiz.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityRepositoryInterface $entity_repository,
    LanguageManagerInterface $language_manager,
    Registry $theme_registry,
    EntityDisplayRepositoryInterface $entity_display_repository,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountProxyInterface $currentUser,
    protected Renderer $renderer,
    protected Token $token,
    protected Config $config,
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
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('renderer'),
      $container->get('token'),
      $container->get('config.factory')->get('quiz.settings'),
    );
    $view_builder->setMessenger($container->get('messenger'));
    return $view_builder;
  }

  /**
   * Alters the build array for quiz results.
   */
  public function alterBuild(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, $view_mode) {
    /** @var \Drupal\quiz\Entity\QuizResult $entity */
    $render_controller = $this->entityTypeManager->getViewBuilder('quiz_result_answer');

    if ($entity->get('is_invalid')->value && ($this->currentUser->id() == $entity->get('uid')->getString())) {
      $this->messenger()->addWarning($this->t('Your previous score on this @quiz was equal or better. This result will not be saved.', ['@quiz' => QuizUtil::getQuizName()]));
    }

    if (!$entity->is_evaluated && empty($_POST)) {
      $msg = $this->t('Parts of this @quiz have not been evaluated yet. The score below is not final.', ['@quiz' => QuizUtil::getQuizName()]);
      $this->messenger()->addWarning($msg);
    }

    $score = $entity->score();

    $account = User::load($entity->get('uid')->getString());

    if ($display->getComponent('questions')) {
      $questions = [];
      foreach ($entity->getLayout() as $qra) {
        // Loop through all the questions and get their feedback.
        /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
        $storage = $this->entityTypeManager->getStorage('quiz_question');
        /** @var \Drupal\quiz\Entity\QuizQuestion $question */
        $question = $storage->loadRevision($qra->get('question_vid')->getString());

        if (!$question) {
          // Question went missing...
          continue;
        }

        if ($question->hasFeedback() && $entity->hasReview()) {
          $feedback = $render_controller->view($qra);
          $feedback_rendered = $this->renderer->renderRoot($feedback);
          if ($feedback_rendered) {
            $questions[$question->id()] = [
              '#title' => $this->t('Question @num', ['@num' => $qra->get('display_number')->getString()]),
              '#type' => 'fieldset',
              'feedback' => ['#markup' => $feedback_rendered],
              '#weight' => $qra->get('number')->getString(),
            ];
          }
        }
      }
      if ($questions) {
        $build['questions'] = $questions;
      }
    }

    if ($display->getComponent('summary') && $entity->canReview('quiz_feedback')) {
      $summary = $this->getSummaryText($entity);
      $build['summary'] = [
        '#theme' => 'quiz_result_summary',
        '#quiz_result' => $entity,
        '#summary_passfail' => !empty($summary['passfail']) ? $summary['passfail'] : NULL,
        '#summary_range' => !empty($summary['result']) ? $summary['result'] : NULL,
        '#attributes' => new Attribute([
          'id' => 'quiz-summary',
        ]),
      ];
    }

    if ($display->getComponent('score') && $entity->canReview('score')) {
      $build['score'] = [
        '#theme' => 'quiz_result_score',
        '#quiz_result' => $entity,
        '#numeric_score' => $score['numeric_score'],
        '#percentage_score' => $score['percentage_score'],
        '#question_count' => $score['possible_score'],
        '#username' => ($account->id() == $this->currentUser->id()) ? t('You') : $account->getDisplayName(),
        '#your_total' => ($account->id() == $this->currentUser->id()) ? t('Your') : t('Total'),
        '#possible_attributes' => new Attribute([
          'id' => 'quiz_score_possible',
        ]),
        '#percent_attributes' => new Attribute([
          'id' => 'quiz_score_percent',
        ]),
      ];
    }

    if (!Element::children($build)) {
      $build['no_feedback_text']['#markup'] = $this->t('You have finished this @quiz.', ['@quiz' => QuizUtil::getQuizName()]);
    }

    // The visibility of feedback may change based on time or other conditions.
    $build['#cache']['max-age'] = 0;

    return $build;
  }

  /**
   * Get the summary message for a completed quiz result.
   *
   * Summary is determined by the pass/fail configurations on the quiz.
   *
   * @param \Drupal\quiz\Entity\QuizResult $quiz_result
   *   The quiz result.
   *
   * @return array
   *   Render array.
   */
  public function getSummaryText(QuizResult $quiz_result): array {
    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('quiz');
    /** @var \Drupal\quiz\Entity\Quiz $quiz */
    $quiz = $storage->loadRevision($quiz_result->get('vid')->getString());

    $account = $quiz_result->getOwner();
    $token_types = [
      'global' => NULL,
      'node' => $quiz,
      'user' => $account,
      'quiz_result' => $quiz_result,
    ];
    $summary = [];

    if ($paragraph = $this->getRangeFeedback($quiz, $quiz_result->get('score')->getString())) {
      // Found quiz feedback based on a grade range.
      $paragraph_text = $paragraph->get('quiz_feedback')->get(0)->getValue();
      $summary['result'] = check_markup($this->token->replace($paragraph_text['value'], $token_types), $paragraph_text['format']);
    }

    $pass_text = $quiz->get('summary_pass')->getValue()[0];
    $default_text = $quiz->get('summary_default')->getValue()[0];

    if ($this->config->get('use_passfail', 1) && $quiz->get('pass_rate')->getString() > 0) {
      if ($quiz_result->get('score')->getString() >= $quiz->get('pass_rate')->getString()) {
        // Pass/fail is enabled and user passed.
        $summary['passfail'] = check_markup($this->token->replace($pass_text['value'], $token_types), $pass_text['format']);
      }
      else {
        // User failed.
        $summary['passfail'] = check_markup($this->token->replace($default_text['value'], $token_types), $default_text['format']);
      }
    }
    else {
      // Pass/fail is not being used so display the default.
      $summary['passfail'] = check_markup($this->token->replace($default_text['value'], $token_types), $default_text['format']);
    }

    return $summary;
  }

  /**
   * Get summary text for a particular score from a set of result options.
   *
   * @param \Drupal\quiz\Entity\Quiz $quiz
   *   The quiz.
   * @param int $score
   *   The percentage score.
   *
   * @return \Drupal\paragraphs\Entity\Paragraph|null
   *   The Paragraph which has the score value in it.
   */
  public function getRangeFeedback(Quiz $quiz, int $score): ?Paragraph {
    foreach ($quiz->get('result_options')->referencedEntities() as $paragraph) {
      $range = $paragraph->get('quiz_feedback_range')->get(0)->getValue();
      if ($score >= $range['from'] && $score <= $range['to']) {
        return $paragraph;
      }
    }
    return NULL;
  }

}
