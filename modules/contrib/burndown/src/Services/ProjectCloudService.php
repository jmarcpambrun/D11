<?php

namespace Drupal\burndown\Services;

use Drupal\burndown\Entity\Project;
use Drupal\burndown\Entity\Task;

/**
 * Provides a Service for generating a word cloud of Projects.
 */
class ProjectCloudService {

  /**
   * Constructs a new ProjectCloudService object.
   */
  public function __construct() {

  }

  /**
   * Build a weighted (by number of open tasks) array of all projects.
   *
   * @see https://stackoverflow.com/questions/227/whats-the-best-way-to-generate-a-tag-cloud-from-an-array-using-h1-through-h6-fo
   */
  public function getProjectCloud() {
    $values = [];
    $tags = [];

    // Min/max sizing (i.e. px size).
    $max_size = 30;
    $min_size = 11;

    // Get all projects.
    $projects = Project::loadMultiple();

    // Count open tasks per project.
    if (!empty($projects)) {
      foreach ($projects as $project) {
        if ($project->getStatus() != 1) {
          continue;
        }
        $shortcode = $project->getShortcode();
        $num_tasks = Task::getOpenTasksFor($shortcode);
        // Prevent divide by zero.
        if ($num_tasks == 0) {
          $num_tasks = 1;
        }
        $values[$shortcode] = $num_tasks;
      }
    }

    if (!empty($values)) {
      ksort($values);

      // Get min/max number of tasks.
      $max_qty = max(array_values($values));
      $min_qty = min(array_values($values));

      // Find the range of values.
      $spread = $max_qty - $min_qty;
      if (0 == $spread) {
        $spread = 1;
      }

      // Determine the font-size increment.
      $step = ($max_size - $min_size) / $spread;

      // Updated our array with weighted values.
      foreach ($values as $shortcode => $value) {
        // Calculate CSS font-size:
        // * Find the $value in excess of $min_qty
        // * Multiply by the font-size increment ($size)
        // * Add the $min_size set above.
        $size = $min_size + (($value - $min_qty) * $step);
        $size = ceil($size);

        // Get first letter of project code (for coloration).
        $first_letter = strtoupper(substr($shortcode, 0, 1));

        // Update our array.
        $tags[$shortcode] = [
          'shortcode' => $shortcode,
          'first_letter' => $first_letter,
          'size' => $size,
        ];
      }
    }

    // Return our array.
    return $tags;
  }

}
