<?php

/**
 * @file
 * Contains burndown_project.page.inc.
 *
 * Page callback for Project entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Project templates.
 *
 * Default template: burndown_project.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_burndown_project(array &$variables) {
  // Fetch Project Entity Object.
  $burndown_project = $variables['elements']['#burndown_project'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
