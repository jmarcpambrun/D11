<?php

declare(strict_types=1);

namespace Drupal\quiz\Services;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\quiz\Plugin\QuizQuestionPluginManager;
use Drupal\quiz\Util\QuizUtil;

/**
 * Provides helper methods for quiz functionality.
 */
class QuizHelper {

  use StringTranslationTrait;

  public function __construct(
    protected QuizQuestionPluginManager $pluginManager,
    protected ModuleHandlerInterface $moduleHandler,
    protected EntityDisplayRepositoryInterface $entityDisplayRepository,
    protected MessengerInterface $messenger,
  ) {}

  /**
   * Retrieve question type plugins.
   *
   * @return array
   *   Array of question types.
   */
  public function getQuestionTypes(): array {
    $plugins = $this->pluginManager->getDefinitions();
    if (empty($plugins)) {
      $this->messenger->addWarning($this->t('You need to install and enable at least one question type to use Quiz.'));
    }
    return $plugins;
  }

  /**
   * Format a number of seconds to a hh:mm:ss format.
   *
   * @param int $time_in_sec
   *   Time in seconds.
   *
   * @return string
   *   Formatted time string.
   */
  public function formatDuration(int $time_in_sec): string {
    $hours = intval($time_in_sec / 3600);
    $min = intval(($time_in_sec - $hours * 3600) / 60);
    $sec = $time_in_sec % 60;
    if (strlen((string) $min) == 1) {
      $min = '0' . $min;
    }
    if (strlen((string) $sec) == 1) {
      $sec = '0' . $sec;
    }
    return "$hours:$min:$sec";
  }

  /**
   * Get the feedback options for Quizzes.
   *
   * @return array
   *   Array of feedback option labels keyed by machine name.
   */
  public function getFeedbackOptions(): array {
    $feedback_options = $this->moduleHandler->invokeAll('quiz_feedback_options');

    $view_modes = $this->entityDisplayRepository->getViewModes('quiz_question');
    $feedback_options['quiz_question_view_full'] = $this->t('Question: Full');
    foreach ($view_modes as $view_mode => $info) {
      $feedback_options['quiz_question_view_' . $view_mode] = $this->t('Question: @label', ['@label' => $info['label']]);
    }

    $feedback_options += [
      'attempt' => $this->t('Attempt'),
      'choice' => $this->t('Choices'),
      'correct' => $this->t('Whether correct'),
      'score' => $this->t('Score'),
      'answer_feedback' => $this->t('Answer feedback'),
      'question_feedback' => $this->t('Question feedback'),
      'solution' => $this->t('Correct answer'),
      'quiz_feedback' => $this->t('@quiz feedback', ['@quiz' => QuizUtil::getQuizName()]),
    ];

    $this->moduleHandler->alter('quiz_feedback_options', $feedback_options);

    return $feedback_options;
  }

  /**
   * Help with special pagination.
   *
   * @param int $total
   *   Total items.
   * @param int|null $perpage
   *   Items per page.
   * @param int|null $current
   *   Current page.
   * @param int|null $siblings
   *   Number of sibling pages to show.
   *
   * @return array
   *   Array of page numbers.
   */
  public function paginationHelper(int $total, ?int $perpage = NULL, ?int $current = NULL, ?int $siblings = NULL): array {
    $result = [];

    if (isset($perpage)) {
      $result = range(1, ceil($total / $perpage));

      if (isset($current, $siblings)) {
        $siblings = (int) (floor($siblings / 2) * 2 + 1);
        if ($siblings >= 1) {
          $offset = (int) max(0, min(count($result) - $siblings, $current - (int) ceil($siblings / 2)));
          $result = array_slice($result, $offset, $siblings);
        }
      }
    }

    return $result;
  }

}
