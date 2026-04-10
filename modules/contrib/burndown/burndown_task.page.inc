<?php

/**
 * @file
 * Contains burndown_task.page.inc.
 *
 * Page callback for Task entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Task templates.
 *
 * Default template: burndown_task.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_burndown_task(array &$variables) {
  // Fetch Task Entity Object.
  $burndown_task = $variables['elements']['#burndown_task'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }

  $variables['id'] = $burndown_task->id();
  $variables['ticket_id'] = $burndown_task->getTicketId();
  $variables['name'] = $burndown_task->getName();
  $variables['shortcode'] = $burndown_task->getProject()->getShortcode();
  $variables['backlog'] = $burndown_task->inBacklog();
  $variables['board'] = $burndown_task->onBoard();
  $variables['sprint'] = $burndown_task->isSprint();
  $variables['priority'] = $burndown_task->getPriority();
  $variables['estimate_type'] = $burndown_task->getEstimateType();
  $variables['estimate'] = $burndown_task->getEstimate();
  $variables['assigned_to'] = $burndown_task->getAssignedToName();
  $variables['assigned_to_image'] = $burndown_task->getAssignedToImage();
  $variables['assigned_to_first_letter'] = $burndown_task->getAssignedToFirstLetter();
  $variables['reporter'] = $burndown_task->getOwnerName();
  $variables['reporter_image'] = $burndown_task->getOwnerImage();
  $variables['reporter_first_letter'] = $burndown_task->getOwnerFirstLetter();
  $variables['is_completed'] = $burndown_task->isCompleted();
  $variables['tags'] = $burndown_task->getTagsFormatted();
}
