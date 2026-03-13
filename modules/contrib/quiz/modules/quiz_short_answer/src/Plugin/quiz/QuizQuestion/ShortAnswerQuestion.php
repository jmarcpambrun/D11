<?php

namespace Drupal\quiz_short_answer\Plugin\quiz\QuizQuestion;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\quiz\Attribute\QuizQuestion as QuizQuestionAttribute;
use Drupal\quiz\Entity\QuizQuestion;
use Drupal\quiz\Entity\QuizResultAnswer;

/**
 * Short answer question plugin.
 */
#[QuizQuestionAttribute(
  id: 'short_answer',
  label: new TranslatableMarkup('Short answer question'),
  handlers: ['response' => ShortAnswerResponse::class],
)]
class ShortAnswerQuestion extends QuizQuestion {

  use StringTranslationTrait;

  // Constants for answer matching options.
  const ANSWER_MATCH = 0;

  const ANSWER_INSENSITIVE_MATCH = 1;

  const ANSWER_REGEX = 2;

  const ANSWER_MANUAL = 3;

  /**
   * {@inheritdoc}
   */
  public function getAnsweringForm(FormStateInterface $form_state, QuizResultAnswer $quizQuestionResultAnswer): array {
    $element = parent::getAnsweringForm($form_state, $quizQuestionResultAnswer);

    $element += [
      '#type' => 'textfield',
      '#title' => $this->t('Answer'),
      '#description' => $this->t('Enter your answer here'),
      '#default_value' => '',
      '#size' => 60,
      '#maxlength' => 256,
      '#required' => FALSE,
      '#attributes' => ['autocomplete' => 'off'],
    ];

    if ($quizQuestionResultAnswer->isAnswered()) {
      $element['#default_value'] = $quizQuestionResultAnswer->getResponse();
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function getAnsweringFormValidate(array &$element, FormStateInterface $form_state): void {
    if ($element['#value'] == '') {
      $form_state->setError($element, t('You must provide an answer.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getMaximumScore(): int {
    return \Drupal::config('quiz_short_answer.settings')
      ->get('default_max_score');
  }

}
