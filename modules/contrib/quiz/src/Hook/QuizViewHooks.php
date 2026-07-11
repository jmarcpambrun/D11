<?php

declare(strict_types=1);

namespace Drupal\quiz\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\views\ViewExecutable;

/**
 * Views hook implementations for quiz.
 */
class QuizViewHooks {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_views_data_alter().
   */
  #[Hook('views_data_alter')]
  public function viewsDataAlter(&$data): void {
    $data['quiz_result']['quiz_result_answers'] = [
      'title' => 'All answers',
      'help' => 'Display all answers for a Quiz result in separate columns.',
      'field' => [
        'id' => 'quiz_result_answers',
      ],
    ];
    $data['quiz_result']['quiz_result_answer'] = [
      'title' => 'Single answers',
      'help' => 'Display an answer for a specific question in a Quiz result.',
      'field' => [
        'id' => 'quiz_result_answer',
      ],
    ];
  }

  /**
   * Implements hook_views_pre_view().
   */
  #[Hook('views_pre_view')]
  public function viewsPreView(ViewExecutable $view, string $display_id, array &$args): void {
    $fields = $view->getHandlers('field');

    foreach ($fields as $field_name => $field) {
      if (!empty($args[0]) && $field['id'] === 'quiz_result_answers') {
        $quiz = $this->entityTypeManager
          ->getStorage('quiz')
          ->load($args[0]);
        $i = 0;
        foreach ($quiz->getQuestions() as $quizQuestionRelationship) {
          $quizQuestion = $quizQuestionRelationship->getQuestion();
          if ($quizQuestion->isGraded()) {
            $i++;
            $newfield = [];
            $newfield['id'] = 'quiz_result_answer';
            $newfield['field'] = 'quiz_result_answer';
            $newfield['table'] = 'quiz_result';
            $newfield['alter'] = [];
            $newfield['label'] = $this->t('@num. @question', [
              '@num' => $i,
              '@question' => $quizQuestion->get('title')->value,
            ]);
            $newfield['qqid'] = $quizQuestion->id();
            $newfield['entity_type'] = 'quiz_result';
            $newfield['plugin_id'] = 'quiz_result_answer';
            $view->setHandler($view->current_display, 'field', 'answer_' . $quizQuestion->id(), $newfield);
          }
        }
        // Remove placeholder field.
        $view->setHandlerOption($view->current_display, 'field', $field_name, 'exclude', TRUE);
      }
    }
  }

}
