<?php

namespace Drupal\burndown\Form;

use Drupal\burndown\Entity\Swimlane;
use Drupal\burndown\Entity\Task;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for Task edit forms.
 *
 * @ingroup burndown
 */
class TaskForm extends ContentEntityForm {

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  /**
   * The request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;
  /**
   * Drupal\Core\Render\RendererInterface definition.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    $instance = parent::create($container);
    $instance->account = $container->get('current_user');
    $instance->request = $container->get('request_stack')->getCurrentRequest();
    $instance->renderer = $container->get('renderer');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    // Disable form cache so that the image upload can work.
    $form_state->disableCache();

    if (!$this->entity->isNew()) {
      $form['new_revision'] = [
        '#type' => 'hidden',
        '#title' => $this->t('Create new revision'),
        '#default_value' => TRUE,
        '#weight' => 10,
      ];

      // When we AJAX load the task edit form, we need to properly
      // close it after.
      $params = $this->request->query->all();
      if (isset($params['_wrapper_format']) &&
        (($params['_wrapper_format'] == 'drupal_ajax') ||
        ($params['_wrapper_format'] == 'drupal_modal'))) {
        $form['actions']['submit']['#submit'][] = '_burndown_task_ajax_submit';
        $form['actions']['submit']['#attributes']['class'][] = 'use-ajax-submit';
      }
    }

    // Set estimate options based on project.
    /** @var \Drupal\burndown\Entity\Task $task */
    $task = $this->entity;
    $project = $task->getProject();
    $options = [];
    if (!is_null($project)) {
      $options = $project->getEstimateSizes();
    }

    // Remove delete button (too easy to accidentally press).
    unset($form['actions']['delete']);

    if (empty($options)) {
      $form['estimate']['#access'] = FALSE;
    }
    else {
      // Put standard 'none' option at the top.
      $none_option = [
        '_none' => $this->t("- None -"),
      ];
      $options = array_merge($none_option, $options);

      // Update the form widget.
      $form['estimate']['widget']['#options'] = $options;
    }

    // Scope the Column selector to this project's columns.
    $swimlanes = FALSE;
    if (!is_null($project)) {
      $swimlanes = Swimlane::loadForProject($project->getShortcode());
    }
    $swimlane_options = [];
    $backlog_swimlane_id = NULL;
    if ($swimlanes !== FALSE) {
      $backlog_swimlanes = [];
      $normal_swimlanes = [];
      $completed_swimlanes = [];

      foreach ($swimlanes as $swimlane) {
        if ($swimlane->getShowBacklog()) {
          $backlog_swimlanes[] = $swimlane;
        }
        elseif ($swimlane->getShowCompleted()) {
          $completed_swimlanes[] = $swimlane;
        }
        else {
          $normal_swimlanes[] = $swimlane;
        }
      }

      $sort_swimlanes = function (array &$lanes): void {
        usort($lanes, function ($a, $b) {
          $sort_compare = $a->getSortOrder() <=> $b->getSortOrder();
          if ($sort_compare !== 0) {
            return $sort_compare;
          }
          return strnatcasecmp($a->getName(), $b->getName());
        });
      };

      $sort_swimlanes($backlog_swimlanes);
      $sort_swimlanes($normal_swimlanes);
      $sort_swimlanes($completed_swimlanes);

      $ordered_swimlanes = array_merge($backlog_swimlanes, $normal_swimlanes, $completed_swimlanes);

      foreach ($ordered_swimlanes as $swimlane) {
        $swimlane_options[$swimlane->id()] = $swimlane->getName();
        if ($backlog_swimlane_id === NULL && $swimlane->getShowBacklog()) {
          $backlog_swimlane_id = $swimlane->id();
        }
      }
    }
    $default_swimlane = $task->get('swimlane')->target_id;
    if (is_null($default_swimlane)) {
      if (!is_null($backlog_swimlane_id)) {
        $default_swimlane = $backlog_swimlane_id;
      }
      elseif (!empty($swimlane_options)) {
        $default_swimlane = array_key_first($swimlane_options);
      }
    }

    // Always render Column as a select list on add/edit forms.
    if (isset($form['swimlane']['widget'][0]['target_id'])) {
      $form['swimlane']['#access'] = TRUE;
      $form['swimlane']['widget'][0]['target_id']['#type'] = 'select';
      $form['swimlane']['widget'][0]['target_id']['#options'] = $swimlane_options;
      $form['swimlane']['widget'][0]['target_id']['#default_value'] = $default_swimlane;

      // Remove autocomplete-only settings when forcing select rendering.
      unset($form['swimlane']['widget'][0]['target_id']['#target_type']);
      unset($form['swimlane']['widget'][0]['target_id']['#selection_handler']);
      unset($form['swimlane']['widget'][0]['target_id']['#selection_settings']);
      unset($form['swimlane']['widget'][0]['target_id']['#tags']);
      unset($form['swimlane']['widget'][0]['target_id']['#autocreate']);
      unset($form['swimlane']['widget'][0]['target_id']['#process_default_value']);
      unset($form['swimlane']['widget'][0]['target_id']['#validate_reference']);
    }
    elseif (isset($form['swimlane']['widget'])) {
      $form['swimlane']['#access'] = TRUE;
      $form['swimlane']['widget']['#type'] = 'select';
      $form['swimlane']['widget']['#options'] = $swimlane_options;
      $form['swimlane']['widget']['#default_value'] = $default_swimlane;
    }

    // Close the images section by default to save space.
    $form['images']['widget']['#open'] = FALSE;

    // Add our add/remove from watchlist link.
    if (!$this->entity->isNew()) {
      $user = $this->account;
      $on_list = $task->checkIfOnWatchlist($user);
      if ($on_list !== FALSE) {
        $class = 'watch';
        $link = Link::createFromRoute($this->t('Stop watching this task'),
          'burndown.task_remove_from_watchlist',
          [
            'ticket_id' => $task->getTicketId(),
            'user_id' => $user->id(),
          ],
          ['absolute' => TRUE]
        );
      }
      else {
        $class = 'mute';
        $link = Link::createFromRoute($this->t('Watch this task'),
          'burndown.task_add_to_watchlist',
          [
            'ticket_id' => $task->getTicketId(),
            'user_id' => $user->id(),
          ],
          ['absolute' => TRUE]
        );
      }

      $link = $link->toRenderable();
      $link = $this->renderer->render($link);
      $link = (String) $link;

      $form['watchlist_link'] = [
        '#prefix' => '<div class="watch_list ' . $class . '">',
        '#suffix' => '</div>',
        '#markup' => $link,
        '#weight' => -10,
      ];
    }

    // Reopen link for closed tasks.
    if ($task->getCompleted() == TRUE) {
      $link = Link::createFromRoute($this->t('Reopen this task'),
          'burndown.reopen_task',
          [
            'ticket_id' => $task->getTicketId(),
          ],
          ['absolute' => TRUE]
        );

      $link = $link->toRenderable();
      $link = $this->renderer->render($link);
      $link = (String) $link;

      $form['reopen_task'] = [
        '#prefix' => '<div class="reopen_task">',
        '#suffix' => '</div>',
        '#markup' => $link,
        '#weight' => -9,
      ];
    }

    // Clean up links widget.
    $form['link']['#type'] = 'details';
    $form['link']['#title'] = $this->t('Link(s)');
    $form['link']['#open'] = FALSE;

    if (!$this->entity->isNew()) {
      // Clean up related to widget.
      $form['relationships']['#type'] = 'details';
      $form['relationships']['#title'] = $this->t('Related to');
      $form['relationships']['#open'] = FALSE;
      $form['relationships']['widget']['#title'] = $this->t('Tasks that this task is related to:');
      $form['relationships']['widget']['#access'] = FALSE;
      $form['relationships']['list'] = [
        '#markup' => '<div id="relationships_list"></div>',
      ];
      $form['relationships']['add_new'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => 'add_relationship',
        ],
      ];
      $form['relationships']['add_new']['relationship_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Relationship Type'),
        '#options' => Task::getRelationshipTypes(),
        '#attributes' => [
          'class' => ['add_relationship_select'],
        ],
      ];
      $form['relationships']['add_new']['to_task'] = [
        '#type' => 'entity_autocomplete',
        '#title' => $this->t('Task'),
        '#target_type' => 'burndown_task',
        '#tags' => TRUE,
        '#size' => 15,
        '#maxlength' => 25,
        '#attributes' => [
          'class' => ['add_relationship_entity'],
        ],
      ];

      $form['relationships']['add_new']['link'] = [
        '#markup' => $this->t('<a href="#" class="button add_relationship" data-ticket-id="@id">Add Relationship</a>', [
          '@id' => $task->getTicketId(),
        ]),
      ];

      // Hide miscellaneous items.
      $form['ticket_id']['#access'] = FALSE;
      $form['project']['#access'] = FALSE;
      $form['revision_log']['#access'] = FALSE;
      $form['status']['#access'] = FALSE;
      $form['sprint']['#access'] = FALSE;
      $form['backlog_sort']['#access'] = FALSE;
      $form['board_sort']['#access'] = FALSE;
      $form['completed']['#access'] = FALSE;
      $form['resolution']['#access'] = FALSE;
      $form['log']['#access'] = FALSE;
    }

    // -----------------------
    // Add our log container
    // -----------------------
    if (!$this->entity->isNew()) {
      // Add a placeholder for the log section.
      $form['log'] = [
        '#type' => 'details',
        '#title' => $this->t('Log'),
        '#open' => TRUE,
        '#weight' => 35,
      ];
      // "Tabs" to load different types.
      $form['log']['tabs'] = [
        '#prefix' => '<div class="log_tabs">',
        '#suffix' => '</div>',
      ];
      $form['log']['tabs']['comment'] = [
        '#markup' => $this->t('<a href="#" class="comment">%label</a>', [
          '%label' => 'Comments',
        ]),
      ];
      $form['log']['tabs']['changed'] = [
        '#markup' => $this->t('<a href="#" class="changed">%label</a>', [
          '%label' => 'Changes',
        ]),
      ];
      $form['log']['tabs']['work'] = [
        '#markup' => $this->t('<a href="#" class="work">%label</a>', [
          '%label' => 'Work Logs',
        ]),
      ];
      $form['log']['tabs']['all'] = [
        '#markup' => $this->t('<a href="#" class="all">%label</a>', [
          '%label' => 'All',
        ]),
      ];

      // Container for ajax-loaded logs.
      $form['log']['container'] = [
        '#prefix' => '<div id="burndown_task_log" data-ticket-id="' . $task->getTicketId() . '">',
        '#suffix' => '</div>',
      ];

      // Comment form.
      $form['log']['comment'] = [
        '#type' => 'container',
        '#title' => $this->t('Add a comment'),
        '#attributes' => [
          'class' => ['add_comment'],
        ],
      ];
      $form['log']['comment']['body'] = [
        '#type' => 'textarea',
        '#attributes' => [
          'class' => ['add_comment_text'],
        ],
      ];
      $form['log']['comment']['link'] = [
        '#markup' => $this->t('<a href="#" class="button">%label</a>', [
          '%label' => 'Add Comment',
        ]),
      ];

      // Work log form.
      $form['log']['work'] = [
        '#type' => 'container',
        '#title' => $this->t('Add a work log'),
        '#attributes' => [
          'class' => ['add_work'],
        ],
      ];
      $form['log']['work']['body'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Comment'),
        '#attributes' => [
          'class' => ['add_work_text'],
        ],
      ];
      $form['log']['work']['quantity'] = [
        '#type' => 'number',
        '#title' => $this->t('Time'),
        '#min' => 0,
        '#default_value' => 0,
        '#attributes' => [
          'class' => ['add_work_quantity'],
        ],
      ];
      $form['log']['work']['quantity_type'] = [
        '#type' => 'select',
        '#options' => [
          'm' => $this->t('Minutes'),
          'h' => $this->t('Hours'),
          'd' => $this->t('Days'),
        ],
        '#default_value' => 'h',
        '#attributes' => [
          'class' => ['add_work_quantity_type'],
        ],
      ];
      $form['log']['work']['link'] = [
        '#markup' => $this->t('<a href="#" class="button">%label</a>', [
          '%label' => 'Add Work Log',
        ]),
      ];
    }

    // Add "assign to me" link.
    $form['assigned_to']['widget'][0]['assign_to_me'] = [
      '#markup' => $this->t('<a href="#" class="assign_to_me">%label</a>', [
        '%label' => 'Assign to me',
      ]),
    ];

    // Attach library.
    $form['#attached']['library'][] = 'burndown/drupal.burndown.task_edit';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;

    // Persist Column from the swimlane widget.
    $swimlane_value = $form_state->getValue('swimlane');
    $swimlane_id = NULL;
    if (is_scalar($swimlane_value) && $swimlane_value !== '') {
      $swimlane_id = $swimlane_value;
    }
    elseif (is_array($swimlane_value) && isset($swimlane_value[0]['target_id']) && is_scalar($swimlane_value[0]['target_id']) && $swimlane_value[0]['target_id'] !== '') {
      $swimlane_id = $swimlane_value[0]['target_id'];
    }

    if (!is_null($swimlane_id)) {
      $entity->set('swimlane', $swimlane_id);
    }

    // Save as a new revision if requested to do so.
    if (!$form_state->isValueEmpty('new_revision') && $form_state->getValue('new_revision') != FALSE) {
      $entity->setNewRevision();

      // If a new revision is created, save the current user as revision author.
      $entity->setRevisionCreationTime($this->time->getRequestTime());
      $entity->setRevisionUserId($this->account->id());
    }
    else {
      $entity->setNewRevision(FALSE);
    }

    // Remove artificial fields from form values, as these aren't part of the
    // expected set of values.
    unset($form['relationships']);
    unset($form['log']);

    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label Task.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label Task.', [
          '%label' => $entity->label(),
        ]));
    }
    $form_state->setRedirect('entity.burndown_task.canonical', ['burndown_task' => $entity->id()]);

    return $status;
  }

}
