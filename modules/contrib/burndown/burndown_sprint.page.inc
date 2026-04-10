<?php

/**
 * @file
 * Contains burndown_sprint.page.inc.
 *
 * Page callback for Sprint entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Sprint templates.
 *
 * Default template: burndown_sprint.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_burndown_sprint(array &$variables) {
  // Fetch Sprint Entity Object.
  $burndown_sprint = $variables['elements']['#burndown_sprint'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }

  $variables['id'] = $burndown_sprint->id();
  $variables['name'] = $burndown_sprint->getName();
  $variables['status'] = $burndown_sprint->getStatus();

  // Determine if this sprint can be opened.
  $variables['can_open'] = $burndown_sprint->canOpen();
}
