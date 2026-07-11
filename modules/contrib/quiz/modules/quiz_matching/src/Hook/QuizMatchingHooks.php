<?php

declare(strict_types=1);

namespace Drupal\quiz_matching\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\BundleEntityFormBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\quiz\Entity\QuizQuestionType;

/**
 * Hook implementations for quiz_matching.
 */
class QuizMatchingHooks {

  use StringTranslationTrait;

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, $route_match): string {
    if ($route_name == 'help.page.quiz_matching') {
      return (string) $this->t('A question type for the quiz module: allows you to create matching type questions, which connect terms with one another.');
    }
    return '';
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Hook('form_quiz_question_type_edit_form_alter')]
  public function formQuizQuestionTypeEditFormAlter(array &$form, FormStateInterface $form_state): void {
    if ($form_state->getFormObject() instanceof BundleEntityFormBase) {
      if ($form_state->getFormObject()->getEntity()->id() == 'matching') {
        $config = $this->configFactory->get('quiz_matching.settings');
        $form['shuffle'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Shuffle matching questions'),
          '#default_value' => $config->get('shuffle'),
          '#description' => $this->t('If checked matching questions will be shuffled'),
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

    if ($entity->id() == 'matching') {
      $config = $this->configFactory->getEditable('quiz_matching.settings');
      $config->set('shuffle', $entity->shuffle);
      $config->save();
    }
  }

}
