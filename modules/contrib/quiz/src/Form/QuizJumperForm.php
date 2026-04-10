<?php

namespace Drupal\quiz\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Question jumper form.
 */
class QuizJumperForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $current = $form_state->getBuildInfo()['args'][1];
    $total = $form_state->getBuildInfo()['args'][2];

    $form['question_number'] = [
      '#type' => 'select',
      '#options' => array_combine(range(1, $total), range(1, $total)),
      '#default_value' => $current,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Jump'),
      '#attributes' => ['class' => ['js-hide']],
    ];

    $form['#attached']['library'][] = 'quiz/jumper';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\quiz\Entity\QuizResult $quiz_result */
    $quiz_result = $form_state->getBuildInfo()['args'][0];
    $quiz_result->setQuestion($form_state->getValue('question_number'));
    $form_state->setRedirect('quiz.question.take', [
      'quiz' => $quiz_result->getQuiz()->id(),
      'question_number' => $form_state->getValue('question_number'),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'quiz_jumper_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
  }

}
