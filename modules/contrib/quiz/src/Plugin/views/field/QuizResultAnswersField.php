<?php

namespace Drupal\quiz\Plugin\views\field;

use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;

/**
 * QuizResultAnswersField handler.
 *
 * To provide a field that pulls all answers from quiz results of a
 * specific quiz.
 *
 * @ingroup views_field_handlers
 */
#[ViewsField("quiz_result_answers")]
class QuizResultAnswersField extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Do nothing -- to override the parent query.
  }

}
