<?php

declare(strict_types=1);

namespace Drupal\quiz_long_answer\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\BundleEntityFormBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\quiz\Entity\QuizQuestionType;

/**
 * Hook implementations for quiz_long_answer.
 */
class QuizLongAnswerHooks {

  use StringTranslationTrait;

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, $route_match): string {
    if ($route_name == 'help.page.quiz_long_answer') {
      return '<p>' . (string) $this->t('This module provides long-answer (essay, multi-paragraph) questions to the quiz module.') . '</p>';
    }
    return '';
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Hook('form_quiz_question_type_edit_form_alter')]
  public function formQuizQuestionTypeEditFormAlter(array &$form, FormStateInterface $form_state): void {
    if ($form_state->getFormObject() instanceof BundleEntityFormBase) {
      if ($form_state->getFormObject()->getEntity()->id() == 'long_answer') {
        $config = $this->configFactory->get('quiz_long_answer.settings');
        $form['default_max_score'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Default max score'),
          '#description' => $this->t('Choose the default maximum score for a long answer question.'),
          '#default_value' => $config->get('default_max_score'),
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

    if ($entity->id() == 'long_answer') {
      $config = $this->configFactory->getEditable('quiz_long_answer.settings');
      $config->set('scoring', $entity->scoring);
      $config->save();
    }
  }

}
