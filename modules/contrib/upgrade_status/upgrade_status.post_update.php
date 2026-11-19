<?php

/**
 * @file
 * Post update functions for Upgrade Status.
 */

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\upgrade_status\ScanResultStorage;

/**
 * Removes the settings of Upgrade Status, which nothing used.
 */
function upgrade_status_post_update_remove_unused_settings(): void {
  \Drupal::configFactory()->getEditable('upgrade_status.settings')->delete();
}

/**
 * Removes the stored analysis results, which have fewer checks and old groups.
 */
function upgrade_status_post_update_remove_results_of_older_versions(): TranslatableMarkup {
  // Results of 4.x and of the 5.0.0 alpha releases have the same structure,
  // so the report works with them. But the category of each message and the
  // group of the project are stored with the result, and both changed. The
  // results also have no messages of the newer checks, and no versions of the
  // analysis tools, so the report does not mark them as outdated.
  \Drupal::keyValue(ScanResultStorage::COLLECTION)->deleteAll();
  return t('The analysis results that older versions of Upgrade Status stored were removed, because Upgrade Status 5 has more checks and new categories. Analyze your projects again.');
}
