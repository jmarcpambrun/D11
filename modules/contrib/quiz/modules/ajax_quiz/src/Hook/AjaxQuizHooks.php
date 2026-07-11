<?php

declare(strict_types=1);

namespace Drupal\ajax_quiz\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Hook implementations for ajax_quiz.
 */
class AjaxQuizHooks {

  public function __construct(
    protected AccountProxyInterface $currentUser,
  ) {}

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, $route_match): string {
    if ($route_name == 'help.page.ajax_quiz') {
      return '<p>' . t('AJAX version of quiz. Successive quiz questions will be loaded in the same page without page reload.') . '</p>';
    }
    return '';
  }

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter')]
  public function formAlter(&$form, FormStateInterface $form_state, $form_id): void {
    $quiz_forms = [
      'quiz_question_answering_form',
      'quiz_report_form',
    ];

    if (in_array($form_id, $quiz_forms) && $this->currentUser->hasPermission('access ajax quiz')) {
      $form['#prefix'] = '<div id="ajax-quiz-wrapper">';
      $form['#suffix'] = '</div>';

      $ajax = [
        'wrapper' => 'ajax-quiz-wrapper',
        'method' => 'replace',
        'callback' => 'ajax_quiz_navigate_quiz',
      ];

      $nav_children = Element::children($form['navigation']);
      foreach ($nav_children as $nav_child) {
        if (isset($form['navigation'][$nav_child]['#type']) && $form['navigation'][$nav_child]['#type'] == 'submit') {
          $form['navigation'][$nav_child]['#ajax'] = $ajax;
        }
      }
    }
  }

}
