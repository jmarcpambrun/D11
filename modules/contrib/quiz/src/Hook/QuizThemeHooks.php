<?php

declare(strict_types=1);

namespace Drupal\quiz\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;

/**
 * Theme hook implementations for quiz.
 */
class QuizThemeHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme($existing, $type, $theme, $path): array {
    return [
      'quiz' => [
        'render element' => 'elements',
        'initial preprocess' => static::class . '::preprocessQuiz',
      ],
      'quiz_question' => [
        'render element' => 'elements',
        'initial preprocess' => static::class . '::preprocessQuizQuestion',
      ],
      'quiz_result' => [
        'render element' => 'elements',
        'initial preprocess' => static::class . '::preprocessQuizResult',
      ],
      'quiz_progress' => [
        'variables' => [
          'current' => NULL,
          'total' => NULL,
        ],
      ],
      'question_selection_table' => [
        'render element' => 'form',
      ],
      'quiz_answer_result' => [
        'variables' => [],
      ],
      'quiz_report_form' => [
        'render element' => 'form',
        'path' => $path . '/theme',
        'template' => 'quiz-report-form',
      ],
      'quiz_question_score' => [
        'variables' => ['score' => NULL, 'max_score' => NULL, 'class' => NULL],
        'template' => 'quiz-question-score',
      ],
      'quiz_jumper' => [
        'variables' => ['total' => NULL, 'form' => NULL],
      ],
      'quiz_pager' => [
        'variables' => ['total' => 0, 'current' => 0, 'siblings' => 0],
      ],
      'quiz_questions_page' => [
        'render element' => 'form',
      ],
      'quiz_result_summary' => [
        'variables' => [
          'quiz_result' => NULL,
          'summary_passfail' => NULL,
          'summary_range' => NULL,
          'attributes' => NULL,
        ],
        'template' => 'quiz-result-summary',
      ],
      'quiz_result_score' => [
        'variables' => [
          'quiz_result' => NULL,
          'numeric_score' => NULL,
          'percentage_score' => NULL,
          'question_count' => NULL,
          'username' => NULL,
          'your_total' => NULL,
          'possible_attributes' => NULL,
          'percent_attributes' => NULL,
        ],
        'template' => 'quiz-result-score',
      ],
    ];
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_quiz')]
  public function themeSuggestionsQuiz(array $variables): array {
    $suggestions = [];
    $quiz = $variables['elements']['#quiz'];
    $sanitized_view_mode = strtr($variables['elements']['#view_mode'], '.', '_');
    $suggestions[] = 'quiz__' . $sanitized_view_mode;
    $suggestions[] = 'quiz__' . $quiz->bundle();
    $suggestions[] = 'quiz__' . $quiz->bundle() . '__' . $sanitized_view_mode;
    $suggestions[] = 'quiz__' . $quiz->id();
    $suggestions[] = 'quiz__' . $quiz->id() . '__' . $sanitized_view_mode;

    return $suggestions;
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_quiz_result')]
  public function themeSuggestionsQuizResult(array $variables): array {
    $suggestions = [];
    $quiz_result = $variables['elements']['#quiz_result'];
    $sanitized_view_mode = strtr($variables['elements']['#view_mode'], '.', '_');
    $suggestions[] = 'quiz_result__' . $sanitized_view_mode;
    $suggestions[] = 'quiz_result__' . $quiz_result->bundle();
    $suggestions[] = 'quiz_result__' . $quiz_result->bundle() . '__' . $sanitized_view_mode;
    $suggestions[] = 'quiz_result__' . $quiz_result->id();
    $suggestions[] = 'quiz_result__' . $quiz_result->id() . '__' . $sanitized_view_mode;

    return $suggestions;
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_quiz_result_summary')]
  public function themeSuggestionsQuizResultSummary(array $variables): array {
    $suggestions = [];
    $quiz_result = $variables['quiz_result'];
    $suggestions[] = 'quiz_result_summary__' . $quiz_result->bundle();
    $suggestions[] = 'quiz_result_summary__' . $quiz_result->id();
    return $suggestions;
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_quiz_result_score')]
  public function themeSuggestionsQuizResultScore(array $variables): array {
    $suggestions = [];
    $quiz_result = $variables['quiz_result'];
    $suggestions[] = 'quiz_result_score__' . $quiz_result->bundle();
    $suggestions[] = 'quiz_result_score__' . $quiz_result->id();
    return $suggestions;
  }

  /**
   * Prepares variables for quiz templates.
   */
  public static function preprocessQuiz(array &$variables): void {
    /** @var \Drupal\quiz\Entity\Quiz $quiz */
    $quiz = $variables['elements']['#quiz'];
    $variables['quiz'] = $quiz;
    $variables['content'] = [];
    foreach (Element::children($variables['elements']) as $key) {
      $variables['content'][$key] = $variables['elements'][$key];
    }
  }

  /**
   * Prepares variables for quiz-question templates.
   */
  public static function preprocessQuizQuestion(array &$variables): void {
    /** @var \Drupal\quiz\Entity\QuizQuestion $quiz_question */
    $quiz_question = $variables['elements']['#quiz_question'];
    $variables['quiz_question'] = $quiz_question;
    $variables['content'] = [];
    foreach (Element::children($variables['elements']) as $key) {
      $variables['content'][$key] = $variables['elements'][$key];
    }
  }

  /**
   * Prepares variables for quiz-result templates.
   */
  public static function preprocessQuizResult(array &$variables): void {
    /** @var \Drupal\quiz\Entity\QuizResult $quiz_result */
    $quiz_result = $variables['elements']['#quiz_result'];
    $variables['quiz_result'] = $quiz_result;
    $variables['content'] = [];
    foreach (Element::children($variables['elements']) as $key) {
      $variables['content'][$key] = $variables['elements'][$key];
    }
  }

}
