<?php

/**
 * @file
 * Contains burndown_swimlane.page.inc.
 *
 * Page callback for Column (swimlane) entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Swimlane templates.
 *
 * Default template: burndown_swimlane.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_burndown_swimlane(array &$variables) {
  // Fetch Swimlane Entity Object.
  $burndown_swimlane = $variables['elements']['#burndown_swimlane'];
  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
