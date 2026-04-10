<?php

namespace Drupal\quiz_truefalse\Plugin\quiz\QuizQuestion;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\quiz\Attribute\QuizQuestion as QuizQuestionAttribute;
use Drupal\quiz\Entity\QuizQuestion;
use Drupal\quiz\Entity\QuizResultAnswer;

/**
 * The true/false question plugin.
 */
#[QuizQuestionAttribute(
  id: 'truefalse',
  label: new TranslatableMarkup('True/false question'),
  handlers: ['response' => TrueFalseResponse::class],
)]
class TrueFalseQuestion extends QuizQuestion {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getAnsweringForm(FormStateInterface $form_state, QuizResultAnswer $quizQuestionResultAnswer): array {
    $element = parent::getAnsweringForm($form_state, $quizQuestionResultAnswer);
    $element += [
      '#type' => 'radios',
      '#title' => $this->t('Choose one'),
      '#options' => [
        1 => $this->t('True'),
        0 => $this->t('False'),
      ],
    ];

    if ($quizQuestionResultAnswer->isAnswered()) {
      if ($quizQuestionResultAnswer->getResponse() != '') {
        $element['#default_value'] = $quizQuestionResultAnswer->getResponse();
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function getAnsweringFormValidate(array &$element, FormStateInterface $form_state): void {
    parent::getAnsweringFormValidate($element, $form_state);

    if (is_null($element['#value'])) {
      $form_state->setError($element, t('You must provide an answer.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCreationForm(?array &$form_state = NULL): array {
    $form['correct_answer'] = [
      '#type' => 'radios',
      '#title' => $this->t('Correct answer'),
      '#options' => [
        1 => $this->t('True'),
        0 => $this->t('False'),
      ],
      '#default_value' => $this->node->correct_answer ?? 1,
      '#required' => TRUE,
      '#weight' => -4,
      '#description' => $this->t('Choose if the correct answer for this question is "true" or "false".'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * The maximum points for a true/false question is always 1.
   */
  public function getMaximumScore(): int {
    return 1;
  }

  /**
   * Get the correct answer to this question.
   *
   * This is a utility function. It is not defined in the interface.
   *
   * @return bool
   *   Boolean indicating if the correct answer is TRUE or FALSE
   */
  public function getCorrectAnswer(): bool {
    return $this->get('truefalse_correct')->getString();
  }

}
