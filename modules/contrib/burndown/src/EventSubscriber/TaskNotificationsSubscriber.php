<?php

namespace Drupal\burndown\EventSubscriber;

use Drupal\burndown\Event\TaskChangedEvent;
use Drupal\burndown\Event\TaskCommentEvent;
use Drupal\burndown\Event\TaskCreatedEvent;
use Drupal\Core\Link;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for Task notifications.
 *
 * @package Drupal\burndown\EventSubscriber
 */
class TaskNotificationsSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The event names to listen for, and the methods that should be executed.
   */
  public static function getSubscribedEvents(): array {
    return [
      TaskCreatedEvent::ADDED => 'taskAdded',
      TaskChangedEvent::CHANGED => 'taskChanged',
      TaskCommentEvent::COMMENTED => 'taskCommented',
    ];
  }

  /**
   * React to a task being created.
   *
   * @param Drupal\burndown\Event\TaskCreatedEvent $event
   *   Task added event.
   */
  public function taskAdded(TaskCreatedEvent $event) {
    // Get config object.
    $config = \Drupal::config('burndown.config_settings');

    // Check if email notifications are turned on.
    $emails_enabled = $config->get('enable_email_notifications');

    if ($emails_enabled) {
      // Get task.
      $task = $event->task;
      $ticket_id = $task->getTicketId();
      $title = $task->getName();
      $created_by = $task->getOwnerName();
      $created = $task->getCreatedTime();
      $created = date('r', $created);
      $task_link = Link::createFromRoute($ticket_id,
        'entity.burndown_task.edit_form',
        ['burndown_task' => $task->id()],
        ['absolute' => TRUE]
      );
      $task_link = $task_link->toRenderable();
      $task_link = \Drupal::service('renderer')->renderPlain($task_link);
      $task_link = (String) $task_link;

      // Get project.
      $project = $task->getProject();
      $project_shortcode = $project->getShortcode();
      $project_link = Link::createFromRoute($project_shortcode,
        'burndown.backlog',
        ['shortcode' => $project_shortcode],
        ['absolute' => TRUE]
      );
      $project_link = $project_link->toRenderable();
      $project_link = \Drupal::service('renderer')->renderPlain($project_link);
      $project_link = (String) $project_link;

      // Get watchlist.
      $watchlist = $task->getWatchlist();

      if (!empty($watchlist)) {
        // Set up email.
        $mailManager = \Drupal::service('plugin.manager.mail');
        $key = 'task_added';
        $module = 'burndown';
        $send = TRUE;

        $message = $created_by . ' created a new task.<br><br>';
        $message .= $project_link . ' / ' . $task_link . '<br><br>';
        $message .= $created_by . ' - ' . $created . '<br><br>';
        $message .= $task->getDescription();

        foreach ($watchlist as $watcher) {
          // Get username, email and language preference.
          $to = $watcher->getEmail();
          $username = $watcher->getDisplayName();
          $langcode = $watcher->getPreferredLangcode();

          // Set up params.
          $params = [
            'ticket_id' => $ticket_id,
            'title' => $title,
            'created_by' => $created_by,
            'username' => $username,
            'message' => $message,
          ];

          // Dispatch email.
          $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
        }
      }
    }
  }

  /**
   * React to a task being edited.
   *
   * @param Drupal\burndown\Event\TaskChangedEvent $event
   *   Task changed event.
   */
  public function taskChanged(TaskChangedEvent $event) {
    // Get config object.
    $config = \Drupal::config('burndown.config_settings');

    // Check if email notifications are turned on.
    $emails_enabled = $config->get('enable_email_notifications');

    if ($emails_enabled) {
      // Get task.
      $task = $event->task;
      $ticket_id = $task->getTicketId();
      $title = $task->getName();
      $created_by = $task->getOwnerName();
      $created = $task->getCreatedTime();
      $created = date('r', $created);
      $task_link = Link::createFromRoute($ticket_id,
        'entity.burndown_task.edit_form',
        ['burndown_task' => $task->id()],
        ['absolute' => TRUE]
      );
      $task_link = $task_link->toRenderable();
      $task_link = \Drupal::service('renderer')->renderPlain($task_link);
      $task_link = (String) $task_link;

      // Get project.
      $project = $task->getProject();
      $project_shortcode = $project->getShortcode();
      $project_link = Link::createFromRoute($project_shortcode,
        'burndown.backlog',
        ['shortcode' => $project_shortcode],
        ['absolute' => TRUE]
      );
      $project_link = $project_link->toRenderable();
      $project_link = \Drupal::service('renderer')->renderPlain($project_link);
      $project_link = (String) $project_link;

      // Get changes.
      $change_list_service = \Drupal::service('burndown_service.change_diff_service');
      $change_list = $change_list_service->getChanges($task);

      // Get watchlist.
      $watchlist = $task->getWatchlist();

      if (!empty($watchlist)) {
        // Set up email.
        $mailManager = \Drupal::service('plugin.manager.mail');
        $key = 'task_changed';
        $module = 'burndown';
        $send = TRUE;

        $message = $created_by . ' edited a task.<br><br>';
        $message .= $project_link . ' / ' . $task_link . '<br><br>';
        $message .= $created_by . ' - ' . $created . '<br><br>';
        $message .= $change_list;

        foreach ($watchlist as $watcher) {
          // Get username, email and language preference.
          $to = $watcher->getEmail();
          $username = $watcher->getDisplayName();
          $langcode = $watcher->getPreferredLangcode();

          // Set up params.
          $params = [
            'ticket_id' => $ticket_id,
            'title' => $title,
            'created_by' => $created_by,
            'username' => $username,
            'message' => $message,
          ];

          // Dispatch email.
          $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
        }
      }
    }
  }

  /**
   * React to a task comment.
   *
   * @param Drupal\burndown\Event\TaskCommentEvent $event
   *   Task comment event.
   */
  public function taskCommented(TaskCommentEvent $event) {
    // Get config object.
    $config = \Drupal::config('burndown.config_settings');

    // Check if email notifications are turned on.
    $emails_enabled = $config->get('enable_email_notifications');

    if ($emails_enabled) {
      // Get comment.
      $comment = $event->comment;

      // Get task.
      $task = $event->task;
      $ticket_id = $task->getTicketId();
      $title = $task->getName();
      $created_by = $task->getOwnerName();
      $created = $task->getCreatedTime();
      $created = date('r', $created);
      $task_link = Link::createFromRoute($ticket_id,
        'entity.burndown_task.edit_form',
        ['burndown_task' => $task->id()],
        ['absolute' => TRUE]
      );
      $task_link = $task_link->toRenderable();
      $task_link = \Drupal::service('renderer')->renderPlain($task_link);
      $task_link = (String) $task_link;

      // Get project.
      $project = $task->getProject();
      $project_shortcode = $project->getShortcode();
      $project_link = Link::createFromRoute($project_shortcode,
        'burndown.backlog',
        ['shortcode' => $project_shortcode],
        ['absolute' => TRUE]
      );
      $project_link = $project_link->toRenderable();
      $project_link = \Drupal::service('renderer')->renderPlain($project_link);
      $project_link = (String) $project_link;

      // Get watchlist.
      $watchlist = $task->getWatchlist();

      if (!empty($watchlist)) {
        // Set up email.
        $mailManager = \Drupal::service('plugin.manager.mail');
        $key = 'task_commented';
        $module = 'burndown';
        $send = TRUE;

        $message = $created_by . ' commented on a task.<br><br>';
        $message .= $project_link . ' / ' . $task_link . '<br><br>';
        $message .= $created_by . ' - ' . $created . '<br><br>';
        $message .= $comment;

        foreach ($watchlist as $watcher) {
          // Get username, email and language preference.
          $to = $watcher->getEmail();
          $username = $watcher->getDisplayName();
          $langcode = $watcher->getPreferredLangcode();

          // Set up params.
          $params = [
            'ticket_id' => $ticket_id,
            'title' => $title,
            'created_by' => $created_by,
            'username' => $username,
            'message' => $message,
          ];

          // Dispatch email.
          $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
        }
      }
    }
  }

}
