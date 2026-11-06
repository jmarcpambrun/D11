<?php

namespace Drupal\maestro_taskconsole\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\maestro\Utility\TaskHandler;
use Drupal\Core\Url;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Component\Serialization\Json;
use Drupal\views\Views;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\maestro\Engine\Exception\MaestroGeneralException;

/**
 * Maestro Task Console Controller.
 */
class MaestroTaskConsoleController extends ControllerBase {

  /**
   * GetTasks method
   * This method is called by the menu router for /taskconsole.
   * The output of this method is the current user's task console.
   */
  public function getTasks($highlightQueueID = 0) {
    global $base_url;
    $config = \Drupal::config('maestro.settings');
    $sitewideToken = $config->get('maestro_sitewide_token');
    if (empty($sitewideToken) || $sitewideToken == '') {
      // We throw an error here.  We must have a sitewide token.
      throw new MaestroGeneralException($this->t('You must have a site-wide token enabled. Please go to the Maestro Admin Settings and set a sitewide token.'));
    }

    // Before we do anything, let's see if we should be running the orchestrator through task console refreshes:
    if ($config->get('maestro_orchestrator_task_console')) {
      \Drupal::service('maestro.orchestration_runner')->run();
    }
    $engine = new MaestroEngine();

    $presentation = \Drupal::service('maestro_taskconsole.presentation');
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['maestro-console']],
      '#cache' => ['max-age' => 0, 'contexts' => ['user']],
    ];
    $task_count = 0;
    $stages = [];
    $build['task_console_table'] = [
      '#type' => 'table',
      // Keep wide task tables inside their column, including in sidebar layouts.
      '#theme_wrappers' => [
        'container' => [
          '#attributes' => [
            'class' => ['taskconsole-table-wrapper'],
            'role' => 'region',
            'aria-label' => $this->t('Workflow tasks'),
            'tabindex' => 0,
          ],
        ],
      ],
      '#header' => [
        ['data' => $this->t('Task'), 'class' => ['maestro-column--task'], 'data-maestro-sort' => 'text'],
        ['data' => $this->t('Flow'), 'class' => ['maestro-column--flow'], 'data-maestro-sort' => 'text'],
        ['data' => $this->t('Assigned'), 'class' => ['maestro-column--assigned'], 'data-maestro-sort' => 'number', 'aria-sort' => 'descending'],
        ['data' => $this->t('Stage'), 'class' => ['maestro-column--stage'], 'data-maestro-sort' => 'number'],
        ['data' => $this->t('Actions'), 'class' => ['maestro-column--actions']],
        ['data' => $this->t('Details'), 'class' => ['maestro-column--details']],
      ],
      '#empty' => $this->t('You have no tasks.'),
      '#attributes' => [
        'class' => ['taskconsole-tasks'],
        'aria-label' => $this->t('Your workflow tasks'),
      ],
    ];

    // Fetch the user's queue items.
    $queueIDs = MaestroEngine::getAssignedTaskQueueIds(\Drupal::currentUser()->id());

    if ($queueIDs) {
      $queueIDs = \Drupal::entityTypeManager()->getStorage('maestro_queue')->getQuery()
        ->accessCheck(FALSE)->condition('id', array_unique($queueIDs), 'IN')
        ->sort('created', 'DESC')->sort('id', 'DESC')->execute();
    }

    foreach (array_unique($queueIDs) as $queueID) {
      $highlight = '';

      /*
       *  Reset the internal static cache for this queue record and then reload it
       *  Doing this because we found in certain cases it was not reflecting actual queue record
       */
      \Drupal::entityTypeManager()->getStorage('maestro_queue')->resetCache([$queueID]);
      $queueRecord = \Drupal::entityTypeManager()->getStorage('maestro_queue')->load($queueID);

      if (!$queueRecord || $queueRecord->is_interactive->getString() !== '1') {
        continue;
      }
      $processID = MaestroEngine::getProcessIdFromQueueId($queueID);
      $processRecord = MaestroEngine::getProcessEntryById($processID);
      if (!$processRecord) {
        continue;
      }
      $queueToken = MaestroEngine::getTokenFromQueueId($queueID);

      if ($highlightQueueID == $queueToken) {
        // Set the highlight for the queue entry.
        $highlight = 'maestro-highlight-task';
      }

      $build['task_console_table'][$queueID]['#attributes'] = ['class' => ['task-record', $highlight], 'id' => 'maestro-task-' . $queueID];

      $build['task_console_table'][$queueID]['task'] = [
        '#plain_text' => $this->t($queueRecord->task_label->getString()),
      ];

      $build['task_console_table'][$queueID]['flow'] = [
        '#plain_text' => $this->t($processRecord->process_name->getString()),
      ];

      $build['task_console_table'][$queueID]['assigned'] = [
        '#wrapper_attributes' => ['data-sort-value' => (int) $queueRecord->created->getString()],
        '#plain_text' => \Drupal::service('date.formatter')->format($queueRecord->created->getString(), 'custom', 'j M Y · H:i'),
      ];

      $templateMachineName = $engine->getTemplateIdFromProcessId($queueRecord->process_id->getString());
      $taskTemplate = $engine->getTemplateTaskByID($templateMachineName, $queueRecord->task_id->getString());
      $template = MaestroEngine::getTemplate($templateMachineName);
      $stage = $stages[$processID] ??= $presentation->stage($processID);
      $build['task_console_table'][$queueID]['stage'] = $stage ? [
        '#type' => 'container',
        '#attributes' => ['class' => ['maestro-stage-badge']],
        'summary' => ['#plain_text' => $stage['summary']],
        'message' => ['#plain_text' => $stage['message'] ? ': ' . $stage['message'] : ''],
      ] : ['#plain_text' => '—'];
      $build['task_console_table'][$queueID]['stage']['#wrapper_attributes'] = [
        'data-sort-value' => $stage && $stage['current'] ? $stage['current'] : '',
      ];
      // Default link title.
      $link = 'Execute';
      $use_modal = FALSE;

      $query_options = ['queueid_or_token' => $queueToken];

      if (is_array($taskTemplate) && is_array($taskTemplate['data'])) {
        if (array_key_exists('data', $taskTemplate) && array_key_exists('modal', $taskTemplate['data']) && $taskTemplate['data']['modal'] == 'modal') {
          $use_modal = TRUE;
        }
      }
      /*
       * If this is an interactive Maestro task, it means we show an Operations Dropbutton form element
       * This is a  button with one or more links where the links can be to a node add/edit or
       * to open up a modal window for an interactive task like a form approval action.
       *
       * We need to determine if we have any special handling for this interactive task. It could be
       * a link to an external system.
       */

      $handler = $queueRecord->handler->getString();
      if ($handler && !empty($handler) && $queueRecord->is_interactive->getString() == '1') {

        $handler = str_replace($base_url, '', $handler);
        $handler_type = TaskHandler::getType($handler);

        $handler_url_parts = UrlHelper::parse($handler);
        $query_options += $handler_url_parts['query'];

        // Let's call a hook here to let people change the name of the link to execute the task if they so choose to do so.
        \Drupal::moduleHandler()->invokeAll('maestro_task_console_interactive_link_alter',
            [&$link, $taskTemplate, $queueRecord, $templateMachineName]
        );

      }
      elseif ($queueRecord->is_interactive->getString() == '1' && empty($handler)) {
        // Handler is empty.  If this is an interactive task and has no handler, we're still OK.  This is an interactive function that uses a default handler then.
        $handler_type = 'function';
      }
      else {
        // We shouldn't be processing this. Skip the rest.
        continue;
      }

      $links = [];

      switch ($handler_type) {
        case 'external':
          $build['task_console_table'][$queueID]['execute']['maestro_link'] =
            [
              '#type' => 'link',
              '#title' => $this->t($link),
              '#url' => Url::fromUri($handler, ['query' => $query_options]),
            ];
          break;

        case 'internal':
          // Let's call a hook here to let people change the name of the link to execute the task if they so choose to do so.
          \Drupal::moduleHandler()->invokeAll('maestro_task_console_interactive_link_alter',
              [&$link, $taskTemplate, $queueRecord, $templateMachineName]
          );

          // Let's call a hook here to let people change the actual link.
          \Drupal::moduleHandler()->invokeAll('maestro_task_console_interactive_url_alter',
              [&$handler, &$query_options, $taskTemplate, $queueRecord, $templateMachineName, 'internal']
          );

          $build['task_console_table'][$queueID]['execute'] = [
            'data' => [
              '#theme' => 'maestro_actions',
              '#links' => [
                'maestro_link' => [
                  'title' => $this->t($link),
                  'url' => Url::fromUserInput($handler, ['query' => $query_options]),
                ],
              ],
            ],
          ];


          break;

        case 'function':
          // Let's call a hook here to let people change the name of the link to execute the task if they so choose to do so.
          \Drupal::moduleHandler()->invokeAll('maestro_task_console_interactive_link_alter',
              [&$link, $taskTemplate, $queueRecord, $templateMachineName]
          );

          // Let's call a hook here to let people change the actual link.
          \Drupal::moduleHandler()->invokeAll('maestro_task_console_interactive_url_alter',
              [&$handler, &$query_options, $taskTemplate, $queueRecord, $templateMachineName, 'function']
          );

          if ($use_modal) {
            $query_options += ['modal' => 'modal'];
            $links[$link] = [
              'title' => $this->t($link),
              'url' => Url::fromRoute('maestro.execute', $query_options),
              'attributes' => [
                'class' => ['use-ajax'],
                'data-dialog-type' => 'modal',
                'data-dialog-options' => Json::encode([
                  'width' => '960',
                  'dialogClass' => 'maestro-task-dialog',
                ]),
              ],
            ];
          }
          else {
            $query_options += ['modal' => 'notmodal'];
            $links[$link] = [
              'title' => $this->t($link),
              'url' => Url::fromRoute('maestro.execute', $query_options),
            ];
          }

          $build['task_console_table'][$queueID]['execute'] = [
            'data' => [
              '#theme' => 'maestro_actions',
              '#links' => $links,
            ],
          ];

          break;

        default:
          $build['task_console_table'][$queueID]['execute'] = [
            '#plain_text' => $this->t('Invalid Link'),
          ];
      }

      /*
       * Provide your own execution links here if you wish
       */
      \Drupal::moduleHandler()->invokeAll('maestro_task_console_alter_execution_link',
          [&$build['task_console_table'][$queueID]['execute'], $taskTemplate, $queueRecord, $templateMachineName]
      );

      // Visible labels keep stacked narrow-layout cells understandable.
      foreach (['task' => $this->t('Task'), 'flow' => $this->t('Flow'), 'assigned' => $this->t('Assigned'), 'stage' => $this->t('Stage'), 'execute' => $this->t('Actions')] as $column => $label) {
        $build['task_console_table'][$queueID][$column]['#wrapper_attributes']['data-label'] = $label;
        $build['task_console_table'][$queueID][$column]['#wrapper_attributes']['class'][] = 'maestro-column--' . ($column === 'execute' ? 'actions' : $column);
      }

      $task_count++;
      $build['task_console_table'][$queueID]['expand'] = ['#plain_text' => ''];
      if ((!empty($template->show_details)) || $stage) {
        $build['task_console_table'][$queueID]['expand'] = $this->detailsToggle($processID, $queueID, FALSE);
        $build['task_console_table'][$queueID . '_ajax'] = [
          '#attributes' => ['id' => $queueID . '_ajax', 'class' => ['maestro-ajax-row']],
          'task' => [
            '#wrapper_attributes' => ['colspan' => 6],
            '#type' => 'container',
            '#attributes' => ['id' => 'maestro-ajax-' . $queueID, 'class' => ['maestro-detail-content']],
          ],
        ];
      }
      $build['task_console_table'][$queueID]['expand']['#wrapper_attributes']['class'][] = 'maestro-column--details';
      $build['task_console_table'][$queueID]['expand']['#wrapper_attributes']['data-label'] = $this->t('Details');
    }
    $build['summary'] = [
      '#type' => 'container',
      '#weight' => -10,
      '#attributes' => ['class' => ['maestro-console__summary']],
      'count' => ['#plain_text' => $this->formatPlural($task_count, '1 task available to you', '@count tasks available to you')],
    ];
    $build['#attached']['library'][] = 'maestro_taskconsole/maestro_taskconsole_css';
    // Css for the status bar.
    $build['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $build['#attached']['drupalSettings'] = [
      'baseURL' => base_path(),
    ];

    return $build;
  }

  /**
   * Method to fetch the status of a process.
   */
  public function getStatus($processID, $queueID) {
    $presentation = \Drupal::service('maestro_taskconsole.presentation');
    $context = $presentation->context($queueID, $processID);
    if (!$context) {
      throw new AccessDeniedHttpException();
    }
    $template = $context['template'];
    $queueRecord = $context['queue'];
    $taskTemplate = $context['task_definition'];
    $templateMachineName = $context['template_id'];
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['maestro-workflow-details']],
      '#cache' => ['max-age' => 0],
      'milestones' => $presentation->milestones($processID, $context['stage']),
      'assignment_context' => \Drupal\maestro\Utility\TaskAssignmentDetails::build($queueRecord),
    ];
    $build['task_summary'] = $build['assignment_context']['summary'];
    $build['task_summary']['#weight'] = -20;
    unset($build['assignment_context']['summary']);
    if (!empty($template->show_details)) {
      $customInformation = '';
      \Drupal::moduleHandler()->invokeAll('maestro_task_console_custominformation_alter',
        [&$customInformation, $taskTemplate, $queueRecord, $templateMachineName]);
      $taskDetails = '';
      // Preserve arbitrary attached Views, their titles, and the existing hook contract.
      foreach ($template->views_attached ?? [] as $machine_name => $attached) {
        $view = Views::getView($machine_name);
        $display = explode(';', $attached['view_display'] ?? 'default')[0];
        if (!$view || !$view->access($display)) {
          continue;
        }
        $view_build = $view->buildRenderable($display, [$processID, $queueID], FALSE);
        if ($view_build) {
          $render = [
            '#theme' => 'taskconsole_views',
            '#task_information' => \Drupal::service('renderer')->render($view_build),
            '#title' => $view->storage->label(),
            '#view_id' => $machine_name,
            '#item_count' => count($view->result ?? []),
          ];
          $taskDetails .= \Drupal::service('renderer')->render($render);
        }
      }
      \Drupal::moduleHandler()->invokeAll('maestro_task_console_taskdetails_alter',
        [&$taskDetails, $taskTemplate, $queueRecord, $templateMachineName]);
      $build['custom_information'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['custom-information']],
        'content' => ['#children' => $customInformation],
      ];
      $build['views'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['maestro-task-details']],
        'content' => ['#children' => $taskDetails],
      ];
    }
    $response = new AjaxResponse();
    $response->addCommand(new HtmlCommand('#maestro-ajax-' . $queueID, $build));
    $response->addCommand(new HtmlCommand('.maestro-status-toggle-' . $queueID, $this->detailsToggle($processID, $queueID, TRUE)));
    $response->addCommand(new InvokeCommand('#' . $queueID . '_ajax', 'addClass', ['maestro-ajax-row--expanded']));
    $response->addCommand(new InvokeCommand('#maestro-task-' . $queueID, 'addClass', ['maestro-task--expanded']));
    $response->addCommand(new InvokeCommand('#maestro-id-ajax-' . $queueID, 'focus'));
    return $response;
  }

  /**
   * Closes workflow details without changing the task.
   */
  public function closeStatus($processID, $queueID) {
    if (!\Drupal::service('maestro_taskconsole.presentation')->context($queueID, $processID)) {
      throw new AccessDeniedHttpException();
    }
    $response = new AjaxResponse();
    $response->addCommand(new HtmlCommand('#maestro-ajax-' . $queueID, ''));
    $response->addCommand(new HtmlCommand('.maestro-status-toggle-' . $queueID, $this->detailsToggle($processID, $queueID, FALSE)));
    $response->addCommand(new InvokeCommand('#' . $queueID . '_ajax', 'removeClass', ['maestro-ajax-row--expanded']));
    $response->addCommand(new InvokeCommand('#maestro-task-' . $queueID, 'removeClass', ['maestro-task--expanded']));
    $response->addCommand(new InvokeCommand('#maestro-id-ajax-' . $queueID, 'focus'));
    return $response;
  }

  /**
   * Builds an accessible AJAX toggle, retaining the existing routes and IDs.
   */
  protected function detailsToggle($processID, $queueID, bool $expanded): array {
    return [
      '#type' => 'link',
      '#title' => $expanded ? $this->t('Hide details') : $this->t('Details'),
      '#id' => 'maestro-id-ajax-' . $queueID,
      '#wrapper_attributes' => ['class' => ['maestro-expand-wrapper', 'maestro-status-toggle-' . $queueID]],
      '#attributes' => [
        'class' => ['maestro-details-toggle'],
        'role' => 'button',
        'aria-expanded' => $expanded ? 'true' : 'false',
        'aria-controls' => 'maestro-ajax-' . $queueID,
      ],
      '#url' => Url::fromRoute($expanded ? 'maestro_taskconsole.status_ajax_close' : 'maestro_taskconsole.status_ajax_open', ['processID' => $processID, 'queueID' => $queueID]),
      '#ajax' => ['progress' => ['type' => 'throbber', 'message' => NULL]],
    ];
  }

}
