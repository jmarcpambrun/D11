<?php

declare(strict_types=1);

namespace Drupal\quiz_multichoice\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\BundleEntityFormBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\quiz\Entity\QuizQuestionType;

/**
 * Hook implementations for quiz_multichoice.
 */
class QuizMultichoiceHooks {

  use StringTranslationTrait;

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, $route_match): string {
    if ($route_name == 'help.page.quiz_multichoice') {
      return (string) $this->t('<p>This module provides a multiple choice question type for Quiz.</p>');
    }
    return '';
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Hook('form_quiz_question_multichoice_form_alter')]
  public function formQuizQuestionMultichoiceFormAlter(array &$form, FormStateInterface $form_state): void {
    $form['#attached']['library'][] = 'quiz_multichoice/helper';
    $form['#attached']['drupalSettings']['quiz_multichoice']['scoring'] = $this->configFactory->get('quiz_multichoice.settings')->get('scoring');

    foreach (Element::children($form['alternatives']['widget']) as $key) {
      if (is_numeric($key)) {
        $form['alternatives']['widget'][$key]['subform']['multichoice_correct']['widget']['value']['#attributes']['data-multichoice-delta'] = $key;
        $form['alternatives']['widget'][$key]['subform']['multichoice_correct']['widget']['value']['#attributes']['class'][] = 'quiz-multichoice-correct-checkbox';
      }
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Hook('form_quiz_question_type_edit_form_alter')]
  public function formQuizQuestionTypeEditFormAlter(array &$form, FormStateInterface $form_state): void {
    if ($form_state->getFormObject() instanceof BundleEntityFormBase) {
      if ($form_state->getFormObject()->getEntity()->id() == 'multichoice') {
        $config = $this->configFactory->get('quiz_multichoice.settings');
        $form['scoring'] = [
          '#type' => 'radios',
          '#title' => $this->t('Default scoring method'),
          '#description' => $this->t('Choose the default scoring method for questions with multiple correct answers.'),
          '#options' => [
            0 => $this->t('Give minus one point for incorrect answers'),
            1 => $this->t("Give one point for each incorrect option that haven't been chosen"),
          ],
          '#default_value' => $config->get('scoring'),
        ];
      }
    }
  }

  /**
   * Implements hook_entity_update().
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    if (!$entity instanceof QuizQuestionType) {
      return;
    }

    if ($entity->id() == 'multichoice') {
      $config = $this->configFactory->getEditable('quiz_multichoice.settings');
      $config->set('scoring', $entity->scoring);
      $config->save();
    }
  }

}
