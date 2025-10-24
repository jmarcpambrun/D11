<?php

namespace Drupal\maestro_taskconsole\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\maestro\Utility\TaskHandler;
use Drupal\maestro\Utility\MaestroStatus;
use Drupal\Core\Url;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Component\Serialization\Json;
use Drupal\maestro\Controller\MaestroOrchestrator;
use Drupal\views\Views;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\CssCommand;
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
    if(empty($sitewideToken) || $sitewideToken == '') {
      // We throw an error here.  We must have a sitewide token.
      throw new MaestroGeneralException($this->t('You must have a site-wide token enabled. Please go to the Maestro Admin Settings and set a sitewide token.'));
    }
    
    // Before we do anything, let's see if we should be running the orchestrator through task console refreshes:
    if ($config->get('maestro_orchestrator_task_console')) {
      $orchestrator = new MaestroOrchestrator();
      $orchestrator->orchestrate($config->get('maestro_orchestrator_token'));
    }
    $engine = new MaestroEngine();

    $build = [];
    $build['task_console_table'] = [
      '#type' => 'table',
      '#header' => [$this->t('Task'),
        $this->t('Flow'),
        $this->t('Assigned'),
        $this->t('Actions'),
        $this->t('Details'),
      ],
      '#empty' => $this->t('You have no tasks.'),
      '#attributes' => [
        'class' => ['taskconsole-tasks'],
      ],
    ];

    // Fetch the user's queue items.
    $queueIDs = MaestroEngine::getAssignedTaskQueueIds(\Drupal::currentUser()->id());

    foreach ($queueIDs as $queueID) {
      $highlight = '';
      $url_from_route = FALSE;
      
      /*
       *  Reset the internal static cache for this queue record and then reload it
       *  Doing this because we found in certain cases it was not reflecting actual queue record
       */
      \Drupal::entityTypeManager()->getStorage('maestro_queue')->resetCache([$queueID]);
      $queueRecord = \Drupal::entityTypeManager()->getStorage('maestro_queue')->load($queueID);

      $processID = MaestroEngine::getProcessIdFromQueueId($queueID);
      $processRecord = MaestroEngine::getProcessEntryById($processID);
      $queueToken = MaestroEngine::getTokenFromQueueId($queueID);

      if ($highlightQueueID == $queueToken) {
        // Set the highlight for the queue entry.
        $highlight = 'maestro-highlight-task';
      }



      $build['task_console_table'][$queueID]['#attributes'] = ['class' => ['task-record', $highlight]];

      $build['task_console_table'][$queueID]['task'] = [
        '#plain_text' => $this->t($queueRecord->task_label->getString()),
      ];

      $build['task_console_table'][$queueID]['flow'] = [
        '#plain_text' => $this->t($processRecord->process_name->getString()),
      ];

      $build['task_console_table'][$queueID]['assigned'] = [
        '#plain_text' => \Drupal::service('date.formatter')->format($queueRecord->created->getString(), 'custom', 'Y-m-d H:i:s'),
      ];

      $templateMachineName = $engine->getTemplateIdFromProcessId($queueRecord->process_id->getString());
      $taskTemplate = $engine->getTemplateTaskByID($templateMachineName, $queueRecord->task_id->getString());
      $template = MaestroEngine::getTemplate($templateMachineName);
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

      /*
       * Test to see if this is a URL that can be deduced from a Drupal route or not.
       * if it's not a route, then $url_from_route will be FALSE
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
              '#type' => 'operations',
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
                  'width' => '700',
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
              '#type' => 'operations',
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

      $build['task_console_table'][$queueID]['expand'] = [
        '#wrapper_attributes' => ['class' => ['maestro-expand-wrapper']],
        '#plain_text' => '',
      ];

      $var_workflow_stage_count = intval(MaestroEngine::getProcessVariable('workflow_timeline_stage_count', $processID));
      // If the show details is on OR the status bar is on, we'll show the toggler.
      if ((isset($template->show_details) && $template->show_details) ||
            (isset($template->default_workflow_timeline_stage_count)
            && intval($template->default_workflow_timeline_stage_count) > 0
            && $var_workflow_stage_count > 0)) {
        // Provide details expansion column.  Clicking on it will show the status and/or the task detail information via ajax.
        $build['task_console_table'][$queueID]['expand'] = [
          '#wrapper_attributes' => ['class' => ['maestro-expand-wrapper', 'maestro-status-toggle-' . $queueID]],
          '#attributes' => [
            'class' => ['maestro-timeline-status', 'maestro-status-toggle'],
            'title' => $this->t('Open Details'),
          ],
          '#type' => 'link',
          '#id' => 'maestro-id-ajax-' . $queueID,
          '#url' => Url::fromRoute('maestro_taskconsole.status_ajax_open', ['processID' => $processID, 'queueID' => $queueID]),
          '#title' => $this->t('Open Details'),
          '#ajax' => [
            'progress' => [
              'type' => 'throbber',
              'message' => NULL,
            ],
          ],
        ];

        // Gives the <tr> tag an ID we can target.
        $build['task_console_table'][$queueID . '_ajax']['#attributes']['id'] = $queueID . '_ajax';
        $build['task_console_table'][$queueID . '_ajax']['#attributes']['class'] = ['maestro-ajax-row'];
        $build['task_console_table'][$queueID . '_ajax']['task'] = [
          '#wrapper_attributes' => ['colspan' => count($build['task_console_table'][$queueID]) - 1],
          '#prefix' => '<div id="maestro-ajax-' . $queueID . '">',
          '#suffix' => '</div>',
        ];
      }
    }
    $build['#attached']['library'][] = 'maestro_taskconsole/maestro_taskconsole_css';
    // Css for the status bar.
    $build['#attached']['library'][] = 'maestro/maestro-engine-css';
    $build['#attached']['drupalSettings'] = [
      'baseURL' => base_path(),
    ];

    return $build;
  }

  /**
   * Method to fetch the status of a process.
   */
  public function getStatus($processID, $queueID) {
    $build = [];
    $replace = [];
    $status_bar = '';

    $canExecute = MaestroEngine::canUserExecuteTask($queueID, \Drupal::currentUser()->id());
    if ($canExecute) {
      $queueRecord = \Drupal::entityTypeManager()->getStorage('maestro_queue')->load($queueID);
      $templateMachineName = MaestroEngine::getTemplateIdFromProcessId($processID);
      $taskTemplate = MaestroEngine::getTemplateTaskByID($templateMachineName, $queueRecord->task_id->getString());
      $template = MaestroEngine::getTemplate($templateMachineName);
      $var_workflow_stage_count = intval(MaestroEngine::getProcessVariable('workflow_timeline_stage_count', $processID));

      $build = MaestroStatus::getMaestroStatusBar($processID, $queueID, FALSE);

      // Now determine if we should show the views attached.
      $taskDetails = '';
      $customInformation = '';
      if (isset($template->show_details) && $template->show_details) {
        // We provide an invokation here to allow other modules to inject their own
        // custom information into the task display.
        \Drupal::moduleHandler()->invokeAll('maestro_task_console_custominformation_alter',
            [&$customInformation, $taskTemplate, $queueRecord, $templateMachineName]
        );

        // Lets see if there's any views attached that we should be showing.
        if (isset($template->views_attached)) {
          foreach ($template->views_attached as $machine_name => $arr) {
            $view = Views::getView($machine_name);
            if ($view) {
              $display = explode(';', $arr['view_display']);
              $display_to_use = isset($display[0]) ? $display[0] : 'default';
              $render_build = $view->buildRenderable($display_to_use, [$processID, $queueID], FALSE);
              if ($render_build) {
                $thisViewOutput = \Drupal::service('renderer')->render($render_build);
                if ($thisViewOutput) {
                  $task_information_render_array = [
                    '#theme' => 'taskconsole_views',
                    '#task_information' => $thisViewOutput,
                    '#title' => $view->storage->label(),
                  ];
                  $taskDetails .= (\Drupal::service('renderer')->render($task_information_render_array));
                }
              }
            }
          }
        }

        // Anyone want to override the task details display?
        \Drupal::moduleHandler()->invokeAll('maestro_task_console_taskdetails_alter',
            [&$taskDetails, $taskTemplate, $queueRecord, $templateMachineName]
        );

        $build['custom_information_bar'] = [
          '#children' => '<div class="custom-information">' . $customInformation . '</div>',
        ];

        $build['views_bar'] = [
          '#children' => '<div class="maestro-task-details">' . $taskDetails . '</div>',
        ];

        $replace['expand'] = [
          '#attributes' => [
            'class' => ['maestro-timeline-status', 'maestro-status-toggle-up'],
            'title' => $this->t('Close Details'),
          ],
          '#type' => 'link',
          '#id' => 'maestro-id-ajax-' . $queueID,
          '#url' => Url::fromRoute('maestro_taskconsole.status_ajax_close', ['processID' => $processID, 'queueID' => $queueID]),
          '#title' => $this->t('Close Details'),
          '#ajax' => [
            'progress' => [
              'type' => 'throbber',
              'message' => NULL,
            ],
          ],

        ];
      }
    }
    // We can target the ID of DIV within the table row associated to the expanded task's information as we're able to inject a DIV via the #prefix/#suffix
    // However, we can only inject CSS as a #wrapper_attribute for the "link" and as such, we target the unique class wrapper TD element for the link for replacement.
    $response = new AjaxResponse();
    // Row.
    $response->addCommand(new HtmlCommand('#maestro-ajax-' . $queueID, $build));
    // Wrapper attribute TD tag.
    $response->addCommand(new HtmlCommand('.maestro-status-toggle-' . $queueID . '', $replace['expand']));
    $response->addCommand(new CssCommand('#' . $queueID . '_ajax', ['display' => 'table-row']));
    return $response;
  }

  /**
   * Internal method to close the project status.
   */
  public function closeStatus($processID, $queueID) {
    $build = [];
    $build['expand'] = [
      '#attributes' => [
        'class' => ['maestro-timeline-status', 'maestro-status-toggle'],
        'title' => $this->t('Open Details'),
      ],
      '#type' => 'link',
      '#id' => 'maestro-id-ajax-' . $queueID,
      '#url' => Url::fromRoute('maestro_taskconsole.status_ajax_open', ['processID' => $processID, 'queueID' => $queueID]),
      '#title' => $this->t('Open Details'),
      '#ajax' => [
        'progress' => [
          'type' => 'throbber',
          'message' => NULL,
        ],
      ],

    ];

    $response = new AjaxResponse();
    $response->addCommand(new HtmlCommand('#maestro-ajax-' . $queueID, ''));
    $response->addCommand(new HtmlCommand('.maestro-status-toggle-' . $queueID, $build['expand']));
    $response->addCommand(new CssCommand('#' . $queueID . '_ajax', ['display' => 'none']));

    return $response;
  }

}
