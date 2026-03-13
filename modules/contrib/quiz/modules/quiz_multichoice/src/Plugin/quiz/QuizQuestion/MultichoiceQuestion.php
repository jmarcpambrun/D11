<?php

namespace Drupal\quiz_multichoice\Plugin\quiz\QuizQuestion;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\quiz\Attribute\QuizQuestion as QuizQuestionAttribute;
use Drupal\quiz\Entity\QuizQuestion;
use Drupal\quiz\Entity\QuizResultAnswer;
use function check_markup;

/**
 * Multichoice Question.
 */
#[QuizQuestionAttribute(
  id: 'multichoice',
  label: new TranslatableMarkup('Multiple choice question'),
  handlers: ['response' => MultichoiceResponse::class],
)]
class MultichoiceQuestion extends QuizQuestion {

  use StringTranslationTrait;
  use MessengerTrait;

  /**
   * {@inheritdoc}
   */
  public function save(): ?int {
    // After we save the question, we forgive some possible user errors on the
    // alternatives.
    $saved = parent::save();
    $this->forgive();
    $this->warn();
    return $saved;
  }

  /**
   * Forgive some possible logical flaws in the user input.
   */
  private function forgive(): void {
    $config = \Drupal::config('quiz_multichoice.settings');
    if ($this->get('choice_multi')->value) {
      $alternatives = $this->get('alternatives')->referencedEntities();
      foreach ($alternatives as $alternative) {
        // If the scoring data doesn't make sense, use the data from the
        // "correct" checkbox to set the score data.
        if ($alternative->get('multichoice_score_chosen')->value == $alternative->get('multichoice_score_not_chosen')->value || !is_numeric($alternative->get('multichoice_score_chosen')->value) || !is_numeric($alternative->get('multichoice_score_not_chosen')->value)) {
          if (!empty($alternative->get('multichoice_correct')->value)) {
            $alternative->set('multichoice_score_chosen', 1);
            $alternative->set('multichoice_score_not_chosen', 0);
          }
          else {
            if ($config->get('scoring') == 0) {
              $alternative->set('multichoice_score_chosen', -1);
              $alternative->set('multichoice_score_not_chosen', 0);
            }
            elseif ($config->get('scoring') == 1) {
              $alternative->set('multichoice_score_chosen', 0);
              $alternative->set('multichoice_score_not_chosen', 1);
            }
          }
        }
        $alternative->save();
      }
    }
    else {
      // For questions with one, and only one, correct answer, there will be
      // no points awarded for alternatives not chosen.
      $alternatives = $this->get('alternatives')
        ->referencedEntities();
      foreach ($alternatives as $alternative) {
        if ($alternative->get('multichoice_correct')->value == 1 && $alternative->get('multichoice_score_chosen')->value <= 0) {
          $alternative->set('multichoice_score_chosen', 1);
        }
        if ($alternative->get('multichoice_correct')->value == 0) {
          $alternative->set('multichoice_score_not_chosen', 0);
        }
        $alternative->save();
      }
    }
  }

  /**
   * Warn the user about possible user errors.
   */
  private function warn(): void {
    // Count the number of correct answers.
    $num_corrects = 0;
    $alternatives = $this->get('alternatives')
      ->referencedEntities();
    foreach ($alternatives as $alternative) {
      if ($alternative->get('multichoice_score_chosen')->value > $alternative->get('multichoice_score_not_chosen')->value) {
        $num_corrects++;
      }
    }
    if ($num_corrects == 1 && $this->get('choice_multi')->value == 1 || $num_corrects > 1 && $this->get('choice_multi')->value == 0) {
      $go_back = Url::fromRoute('entity.quiz_question.canonical', ['quiz_question' => $this->id()])->toString();
      if ($num_corrects == 1) {
        $this->messenger()->addWarning(
          $this->t("Your question allows multiple answers. Only one of the alternatives have been marked as correct. If this wasn't intended please <a href=\"@go_back\">go back</a> and correct it.", ['@go_back' => $go_back]), 'warning');
      }
      else {
        $this->messenger()->addWarning(
          $this->t("Your question doesn't allow multiple answers. More than one of the alternatives have been marked as correct. If this wasn't intended please <a href=\"@go_back\">go back</a> and correct it.", ['@go_back' => $go_back]), 'warning');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getAnsweringForm(FormStateInterface $form_state, QuizResultAnswer $quizQuestionResultAnswer): array {
    $element = parent::getAnsweringForm($form_state, $quizQuestionResultAnswer);
    $alternatives = [];
    foreach ($this->get('alternatives')->referencedEntities() as $alternative) {
      /** @var \Drupal\paragraphs\Entity\Paragraph $alternative */
      $uuid = $alternative->get('uuid')->getString();
      $alternatives[$uuid] = $alternative;
    }

    // Build options list.
    $element['user_answer'] = [
      '#type' => 'tableselect',
      '#header' => ['answer' => $this->t('Answer')],
      '#js_select' => FALSE,
      '#multiple' => $this->get('choice_multi')->getString(),
    ];

    // @todo see https://www.drupal.org/project/drupal/issues/2986517
    // There is some way to label the elements.
    foreach ($alternatives as $alternative) {
      $vid = $alternative->getRevisionId();
      $multichoice_answer = $alternative->get('multichoice_answer')->getValue()[0];
      $answer_markup = check_markup($multichoice_answer['value'], $multichoice_answer['format']);
      $element['user_answer']['#options'][$vid]['title']['data']['#title'] = $answer_markup;
      $element['user_answer']['#options'][$vid]['answer'] = $answer_markup;
    }

    if ($this->get('choice_random')->getString()) {
      // We save the choice order so that the order will be the same in the
      // answer report.
      $element['choice_order'] = [
        '#type' => 'hidden',
        '#value' => implode(',', $this->shuffle($element['user_answer']['#options'])),
      ];
    }

    if ($quizQuestionResultAnswer->isAnswered()) {
      $choices = $quizQuestionResultAnswer->getResponse();
      if ($this->get('choice_multi')->getString()) {
        foreach ($choices as $choice) {
          $element['user_answer']['#default_value'][$choice] = TRUE;
        }
      }
      else {
        $element['user_answer']['#default_value'] = reset($choices);
      }
    }

    return $element;
  }

  /**
   * Custom shuffle function.
   *
   * It keeps the array key - value relationship intact.
   *
   * @param array $array
   *   Array to be shuffled.
   *
   * @return array
   *   The shuffled array.
   */
  private function shuffle(array &$array): array {
    $newArray = [];
    $toReturn = array_keys($array);
    shuffle($toReturn);
    foreach ($toReturn as $key) {
      $newArray[$key] = $array[$key];
    }
    $array = $newArray;
    return $toReturn;
  }

  /**
   * {@inheritdoc}
   */
  public function getMaximumScore(): int {
    if ($this->get('choice_boolean')->getString()) {
      // Simple scoring - can only be worth 1 point.
      return 1;
    }

    $maxes = [0];
    foreach ($this->get('alternatives')->referencedEntities() as $alternative) {
      // "Not chosen" could have a positive point amount.
      $maxes[] = max($alternative->get('multichoice_score_chosen')->getString(), $alternative->get('multichoice_score_not_chosen')->getString());
    }

    if ($this->get('choice_multi')->getString() && is_array($maxes)) {
      // For multiple answers, return the maximum possible points of all
      // positively pointed answers.
      return array_sum($maxes);
    }
    else {
      // For a single answer, return the highest pointed amount.
      return max($maxes);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getAnsweringFormValidate(array &$element, FormStateInterface $form_state): void {
    $mcq = $element['#quiz_result_answer']->getQuizQuestion();
    if (!$mcq->get('choice_multi')->getString() && empty($element['user_answer']['#value'])) {
      $form_state->setError($element, (t('You must provide an answer.')));
    }
    parent::getAnsweringFormValidate($element, $form_state);
  }

}
