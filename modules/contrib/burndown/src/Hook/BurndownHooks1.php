<?php

namespace Drupal\burndown\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\burndown\Event\TaskChangedEvent;
use Drupal\burndown\Event\TaskCreatedEvent;
use Drupal\burndown\Entity\Swimlane;
use Drupal\burndown\Entity\Project;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for burndown.
 */
class BurndownHooks1 {
  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      // Main module help for the burndown module.
      case 'help.page.burndown':
        $module_path = \Drupal::service('extension.list.module')->getPath('burndown');
        $text = file_get_contents(DRUPAL_ROOT . '/' . $module_path . '/README.md');
        if (!\Drupal::moduleHandler()->moduleExists('markdown')) {
          return '<pre>' . Html::escape($text) . '</pre>';
        }
        else {
          // Use the Markdown filter to render the README.
          $filter_manager = \Drupal::service('plugin.manager.filter');
          $settings = \Drupal::configFactory()->get('markdown.settings')->getRawData();
          $config = [
            'settings' => $settings,
          ];
          $filter = $filter_manager->createInstance('markdown', $config);
          return $filter->process($text, 'en');
        }
    }
    return NULL;
  }

  /**
   * Implements hook_toolbar().
   */
  #[Hook('toolbar')]
  public function toolbar() {
    $items['burndown'] = [
      '#type' => 'toolbar_item',
      '#attached' => [
        'library' => [
          'burndown/drupal.burndown.toolbar',
        ],
      ],
    ];
    return $items;
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme() {
    $theme = [];
    $theme['burndown'] = [
      'render element' => 'children',
    ];
    $theme['burndown_project'] = [
      'render element' => 'elements',
      'file' => 'burndown_project.page.inc',
      'template' => 'burndown_project',
    ];
    $theme['burndown_project_content_add_list'] = [
      'render element' => 'content',
      'variables' => [
        'content' => NULL,
      ],
      'file' => 'burndown_project.page.inc',
    ];
    $theme['burndown_task'] = [
      'render element' => 'elements',
      'file' => 'burndown_task.page.inc',
    ];
    $theme['burndown_task_content_add_list'] = [
      'render element' => 'content',
      'variables' => [
        'content' => NULL,
      ],
      'file' => 'burndown_task.page.inc',
    ];
    $theme['burndown_swimlane'] = [
      'render element' => 'elements',
      'file' => 'burndown_swimlane.page.inc',
      'template' => 'burndown_swimlane',
    ];
    $theme['burndown_swimlane_content_add_list'] = [
      'render element' => 'content',
      'variables' => [
        'content' => NULL,
      ],
      'file' => 'burndown_swimlane.page.inc',
    ];
    $theme['burndown_sprint'] = [
      'render element' => 'elements',
      'file' => 'burndown_sprint.page.inc',
      'template' => 'burndown_sprint',
    ];
    $theme['burndown_backlog_kanban'] = [
      'variables' => [
        'data' => NULL,
      ],
    ];
    $theme['burndown_backlog_sprint'] = [
      'variables' => [
        'data' => NULL,
      ],
    ];
    $theme['burndown_board'] = [
      'variables' => [
        'data' => NULL,
      ],
    ];
    $theme['burndown_completed_kanban'] = [
      'variables' => [
        'data' => NULL,
      ],
    ];
    $theme['burndown_completed_sprint'] = [
      'variables' => [
        'data' => NULL,
      ],
    ];
    $theme['burndown_task_card'] = [
      'variables' => [
        'data' => NULL,
      ],
    ];
    $theme['burndown_task_row'] = [
      'variables' => [
        'data' => NULL,
      ],
    ];
    $theme['burndown_project_cloud'] = [
      'variables' => [
        'data' => NULL,
        'board' => NULL,
      ],
    ];
    $theme['burndown_sidebar_nav'] = [
      'variables' => [
        'shortcode' => NULL,
        'links' => NULL,
      ],
    ];
    $theme['burndown_multi_bundle_add'] = [
      'variables' => [
        'links' => NULL,
      ],
    ];
    $theme['burndown_log_items'] = [
      'variables' => [
        'data' => NULL,
      ],
    ];
    $theme['burndown_project_swimlanes'] = [
      'variables' => [
        'data' => NULL,
      ],
    ];
    $theme['burndown_task_relationships'] = [
      'variables' => [
        'data' => NULL,
      ],
    ];
    $theme['burndown_user'] = [
      'render element' => 'content',
      'variables' => [
        'assigned_to_image' => NULL,
        'assigned_to' => NULL,
        'assigned_to_first_letter' => NULL,
      ],
      'file' => 'burndown_task.page.inc',
    ];
    return $theme;
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_burndown_project')]
  public function themeSuggestionsBurndownProject(array $variables) {
    $suggestions = [];
    $entity = $variables['elements']['#burndown_project'];
    $sanitized_view_mode = strtr($variables['elements']['#view_mode'], '.', '_');
    $suggestions[] = 'burndown_project__' . $sanitized_view_mode;
    $suggestions[] = 'burndown_project__' . $entity->bundle();
    $suggestions[] = 'burndown_project__' . $entity->bundle() . '__' . $sanitized_view_mode;
    $suggestions[] = 'burndown_project__' . $entity->id();
    $suggestions[] = 'burndown_project__' . $entity->id() . '__' . $sanitized_view_mode;
    return $suggestions;
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_burndown_task')]
  public function themeSuggestionsBurndownTask(array $variables) {
    $suggestions = [];
    $entity = $variables['elements']['#burndown_task'];
    $sanitized_view_mode = strtr($variables['elements']['#view_mode'], '.', '_');
    $suggestions[] = 'burndown_task__' . $sanitized_view_mode;
    $suggestions[] = 'burndown_task__' . $entity->bundle();
    $suggestions[] = 'burndown_task__' . $entity->bundle() . '__' . $sanitized_view_mode;
    $suggestions[] = 'burndown_task__' . $entity->id();
    $suggestions[] = 'burndown_task__' . $entity->id() . '__' . $sanitized_view_mode;
    return $suggestions;
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_burndown_sprint')]
  public function themeSuggestionsBurndownSprint(array $variables) {
    $suggestions = [];
    $entity = $variables['elements']['#burndown_sprint'];
    $sanitized_view_mode = strtr($variables['elements']['#view_mode'], '.', '_');
    $suggestions[] = 'burndown_sprint__' . $sanitized_view_mode;
    $suggestions[] = 'burndown_sprint__' . $entity->bundle();
    $suggestions[] = 'burndown_sprint__' . $entity->bundle() . '__' . $sanitized_view_mode;
    $suggestions[] = 'burndown_sprint__' . $entity->id();
    $suggestions[] = 'burndown_sprint__' . $entity->id() . '__' . $sanitized_view_mode;
    return $suggestions;
  }

  /**
   * Implements hook_page_attachments().
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments) {
    // Add username to drupalSettings.
    $attachments['#attached']['drupalSettings']['user']['name'] = \Drupal::currentUser()->getDisplayName();
  }

  /**
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types) {
    // Add unique shortcode constraint to projects.
    $entity = $entity_types['burndown_project'];
    $entity->addConstraint('ShortcodeUnique');
  }

  /**
   * Implements hook_ENTITY_TYPE_insert().
   *
   * After creating a Project, set up the default Column (swimlane) entities.
   */
  #[Hook('burndown_project_insert')]
  public function burndownProjectInsert(EntityInterface $entity) {
    /** @var \Drupal\burndown\Entity\Project $entity */
    // Get Default Column (swimlane) config entities.
    $storage = \Drupal::entityTypeManager()->getStorage('default_swimlane');
    /** @var \Drupal\burndown\Entity\DefaultSwimlane[] $default_swimlanes */
    $default_swimlanes = $storage->loadMultiple();
    // Get the project.
    $project_id = $entity->id();
    $project = Project::load($project_id);
    // Loop through default lanes and create them.
    foreach ($default_swimlanes as $swimlane_name => $default_swimlane) {
      $swimlane = Swimlane::create([
        'name' => $default_swimlane->label(),
        'status' => 1,
        'project' => [
          'target_id' => $project_id,
        ],
        'sort_order' => $default_swimlane->getSortOrder(),
        'show_backlog' => $default_swimlane->getShowBacklog(),
        'show_project_board' => $default_swimlane->getShowProjectBoard(),
        'show_completed' => $default_swimlane->getShowCompleted(),
      ]);
      $swimlane->save();
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_presave().
   *
   * Change log for tasks.
   */
  #[Hook('burndown_task_presave')]
  public function burndownTaskPresave(EntityInterface $entity) {
    /** @var \Drupal\burndown\Entity\Task $entity */
    // If there's an assignee, add them to the watchlist.
    $assigned_to = $entity->getAssignedTo();
    if (!is_null($assigned_to)) {
      $entity->addToWatchlist($assigned_to);
    }
    // Also add the ticket owner by default.
    $owner = $entity->getOwner();
    if (!is_null($owner)) {
      $entity->addToWatchlist($owner);
    }
    // Get a list of changes on the entity (if any).
    $change_list_service = \Drupal::service('burndown_service.change_diff_service');
    $change_list = $change_list_service->getChanges($entity);
    // Log changes.
    if (!empty($change_list)) {
      $type = 'changed';
      $comment = [
        'value' => $change_list,
        'format' => 'basic_html',
      ];
      $work_done = '';
      $created = time();
      $uid = \Drupal::currentUser()->id();
      $description = 'Task changed';
      $entity->addLog($type, $comment, $work_done, $created, $uid, $description);
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_insert().
   *
   * After creating a Task, issue an event.
   */
  #[Hook('burndown_task_insert')]
  public function burndownTaskInsert(EntityInterface $entity) {
    // Instantiate our event.
    $event = new TaskCreatedEvent($entity, \Drupal::currentUser());
    // Get the event_dispatcher service and dispatch the event.
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $event_dispatcher->dispatch($event, TaskCreatedEvent::ADDED);
  }

  /**
   * Implements hook_ENTITY_TYPE_update().
   *
   * After updating a Task, issue an event.
   */
  #[Hook('burndown_task_update')]
  public function burndownTaskUpdate(EntityInterface $entity) {
    // Instantiate our event.
    $event = new TaskChangedEvent($entity, \Drupal::currentUser());
    // Get the event_dispatcher service and dispatch the event.
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $event_dispatcher->dispatch($event, TaskChangedEvent::CHANGED);
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   *
   * Set estimation options based on project configuration.
   */
  #[Hook('form_burndown_task_task_add_form_alter')]
  public function formBurndownTaskTaskAddFormAlter(&$form, FormStateInterface $form_state, $form_id) {
    // Get Project.
    $shortcode = \Drupal::request()->query->get('shortcode');
    if (!empty($shortcode)) {
      $project = Project::loadFromShortcode($shortcode);
      if ($project !== FALSE) {
        $options = $project->getEstimateSizes();
        if (empty($options)) {
          $form['estimate']['#access'] = FALSE;
        }
        else {
          // Put standard 'none' option at the top.
          $none_option = [
            '_none' => "- None -",
          ];
          $options = array_merge($none_option, $options);
          // Update the form widget.
          $form['estimate']['widget']['#options'] = $options;
        }
      }
    }
    // Close the images section by default to save space.
    $form['images']['widget']['#open'] = FALSE;
    // Clean up links widget.
    $form['link']['#type'] = 'details';
    $form['link']['#title'] = $this->t('Link(s)');
    $form['link']['#open'] = FALSE;
    // Clean up related to widget.
    $form['relationships']['#type'] = 'details';
    $form['relationships']['#title'] = $this->t('Related to');
    $form['relationships']['#open'] = FALSE;
    $form['relationships']['widget']['#title'] = $this->t('Tasks that this task is related to:');
    // Hide miscellaneous items.
    $form['ticket_id']['#access'] = FALSE;
    $form['project']['#access'] = FALSE;
    $form['revision_log']['#access'] = FALSE;
    $form['status']['#access'] = FALSE;
    $form['sprint']['#access'] = FALSE;
    $form['backlog_sort']['#access'] = FALSE;
    $form['board_sort']['#access'] = FALSE;
    $form['watch_list']['#access'] = FALSE;
    $form['completed']['#access'] = FALSE;
    $form['resolution']['#access'] = FALSE;
    $form['log']['#access'] = FALSE;
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   *
   * Clean up the sprint add form.
   */
  #[Hook('form_burndown_sprint_add_form_alter')]
  public function formBurndownSprintAddFormAlter(&$form, FormStateInterface $form_state, $form_id) {
    // If the project has a default value, hide the field.
    $project = $form['project']['widget'][0]['target_id']['#default_value'];
    if (gettype($project) == 'object') {
      $form['project']['#access'] = FALSE;
      $form['status']['#access'] = FALSE;
    }
  }

  /**
   * Implements hook_mail().
   */
  #[Hook('mail')]
  public function mail($key, &$message, $params) {
    $options = [
      'langcode' => $message['langcode'],
    ];
    $message['headers']['Content-Type'] = 'text/html; charset=UTF-8;';
    $message['from'] = \Drupal::config('system.site')->get('mail');
    $message['reply-to'] = $message['from'];
    $message['body'][] = $params['message'];
    switch ($key) {
      case 'task_added':
        $message['subject'] = $this->t('[BURNDOWN] (@ticket_id): @title', [
          '@ticket_id' => $params['ticket_id'],
          '@title' => $params['title'],
        ], $options);
        break;

      case 'task_changed':
        $message['subject'] = $this->t('[BURNDOWN] @created_by edited @ticket_id: @title', [
          '@created_by' => $params['created_by'],
          '@ticket_id' => $params['ticket_id'],
          '@title' => $params['title'],
        ], $options);
        break;

      case 'task_commented':
        $message['subject'] = $this->t('[BURNDOWN] @created_by commented on @ticket_id: @title', [
          '@created_by' => $params['created_by'],
          '@ticket_id' => $params['ticket_id'],
          '@title' => $params['title'],
        ], $options);
        break;
    }
  }

}
