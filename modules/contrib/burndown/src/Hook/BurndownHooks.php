<?php

namespace Drupal\burndown\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Form\FormStateInterface;

/**
 * Hook implementations for the Burndown module.
 */
class BurndownHooks {

  /**
   * Implements hook_views_data_alter().
   *
   * Adds proper filter support for the project field in Views.
   */
  #[Hook('views_data_alter')]
  public function viewsDataAlter(&$data) {
    if (isset($data['burndown_task_field_data']['project'])) {
      // Add a filter definition for the project field.
      $data['burndown_task_field_data']['project']['filter'] = [
        'title' => t('Project'),
        'help' => t('Filter tasks by project'),
        'field' => 'project',
        'id' => 'entity_reference',
        'allow empty' => TRUE,
      ];
    }

    if (isset($data['burndown_task_field_data']['completed']['filter'])) {
      // Treat NULL as FALSE for the completed boolean filter.
      $data['burndown_task_field_data']['completed']['filter']['accept null'] = TRUE;
    }
  }

  /**
   * Implements hook_form_alter().
   *
   * Sorts project options in Views filters alphabetically.
   */
  #[Hook('form_alter')]
  public function formAlter(&$form, FormStateInterface $form_state, $form_id) {
    if ($form_id === 'views_exposed_form' && isset($form['project'])) {
      // Sort project options alphabetically by label.
      $options = $form['project']['#options'] ?? [];
      if (is_array($options)) {
        asort($options);
        $form['project']['#options'] = $options;
      }
    }
  }

}
