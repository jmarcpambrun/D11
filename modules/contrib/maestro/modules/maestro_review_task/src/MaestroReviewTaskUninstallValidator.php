<?php

namespace Drupal\maestro_review_task;

use Drupal\Core\Extension\ModuleUninstallValidatorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\maestro\Engine\MaestroEngine;

/**
 * Prevents uninstalling the module while a template still uses a Review Task.
 */
class MaestroReviewTaskUninstallValidator implements ModuleUninstallValidatorInterface {

  use StringTranslationTrait;

  /**
   * Constructs the validator.
   */
  public function __construct(TranslationInterface $string_translation) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public function validate($module) {
    $reasons = [];
    if ($module !== 'maestro_review_task') {
      return $reasons;
    }
    foreach (MaestroEngine::getTemplates() as $template) {
      foreach ($template->tasks ?? [] as $task) {
        if (($task['tasktype'] ?? '') === 'MaestroReviewTask') {
          $reasons[] = $this->t('To uninstall Maestro Review Task, remove the Review Task "@task" from the <em>@template</em> template.', [
            '@task' => $task['label'],
            '@template' => $template->label(),
          ]);
        }
      }
    }
    return $reasons;
  }

}
