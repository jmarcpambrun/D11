<?php

namespace Drupal\quiz_matching\Plugin\quiz\QuizQuestion;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\quiz\Attribute\QuizQuestion as QuizQuestionAttribute;
use Drupal\quiz\Entity\QuizQuestion;
use Drupal\quiz\Entity\QuizResultAnswer;

/**
 * Matching classes.
 *
 * This module uses the question interface to define the matching question type.
 *
 * A Matching node defines a series of questions and answers and requires the
 * userto associate each answer with a question.
 */
#[QuizQuestionAttribute(
  id: 'matching',
  label: new TranslatableMarkup('Matching question'),
  handlers: ['response' => MatchingResponse::class],
)]
class MatchingQuestion extends QuizQuestion {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getAnsweringForm(FormStateInterface $form_state, QuizResultAnswer $quizQuestionResultAnswer): array {
    $form = parent::getAnsweringForm($form_state, $quizQuestionResultAnswer);

    $answers[''] = '';
    $questions = [];
    foreach ($this->get('quiz_matching')->referencedEntities() as $alternative) {
      /** @var \Drupal\paragraphs\Entity\Paragraph $alternative */
      $questions[$alternative->getRevisionId()] = $alternative->get('matching_question')->getString();
      $answers[$alternative->getRevisionId()] = $alternative->get('matching_answer')->getString();
    }

    foreach ($questions as $id => $question) {
      // Build options list.
      $form['user_answer'][$id] = [
        '#title' => $question,
        '#type' => 'select',
        '#options' => $answers,
      ];
    }

    if ($paragraphs = $quizQuestionResultAnswer->getResponse()) {
      // If this question already has been answered.
      foreach ($paragraphs as $paragraph) {
        if ($paragraph->get('matching_user_answer')->value) {
          $form['user_answer'][$paragraph->get('matching_user_question')->value]['#default_value'] = $paragraph->get('matching_user_answer')->value;
        }
      }
    }

    if (\Drupal::config('quiz_matching.settings')->get('shuffle')) {
      $form['user_answer'] = $this->customShuffle($form['user_answer']);
    }
    $form['scoring_info'] = [
      '#access' => !empty($this->get('choice_penalty')->getString()),
      '#markup' => '<p><em>' . $this->t('You lose points by selecting incorrect options. You may leave an option blank to avoid losing points.') . '</em></p>',
    ];
    return $form;
  }

  /**
   * Question response validator.
   */
  public static function getAnsweringFormValidate(array &$element, FormStateInterface $form_state): void {
    $values = $form_state->getValues();
    $id = $element['#quiz_result_answer']->getQuizQuestion()->id();
    if (!array_filter($values['question'][$id]['answer']['user_answer'])) {
      $form_state->setError($element, t('You need to match at least one of the items.'));
    }
  }

  /**
   * Shuffles an array, but keep the keys.
   *
   * @param array $array
   *   Array to be shuffled.
   *
   * @return array
   *   A shuffled version of the array with keys preserved.
   */
  private function customShuffle(array $array = []): array {
    $new_array = [];
    while (count($array)) {
      $element = array_rand($array);
      $new_array[$element] = $array[$element];
      unset($array[$element]);
    }
    return $new_array;
  }

  /**
   * {@inheritdoc}
   */
  public function getMaximumScore(): int {
    return count($this->get('quiz_matching')->referencedEntities());
  }

  /**
   * Get the correct answers for this question.
   *
   * @return array
   *   Array of correct answers
   */
  public function getCorrectAnswer(): array {
    $correct_answers = [];
    foreach ($this->get('quiz_matching')->referencedEntities() as $entity) {
      $correct_answers[$entity->getRevisionId()] = $entity;
    }
    return $correct_answers;
  }

}
