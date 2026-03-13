<?php

namespace Drupal\drd;

use Drupal\update\UpdateFetcher as CoreUpdateFetcher;

/**
 * Fetches project information from remote locations.
 */
class UpdateFetcher extends CoreUpdateFetcher {

  /**
   * {@inheritdoc}
   */
  public function buildFetchUrl(array $project, $site_key = '') {
    $name = $project['name'];
    $core = empty($project['core']) ? \Drupal::CORE_COMPATIBILITY : $project['core'];
    $url = $this->getFetchBaseUrl($project);
    if ($core[0] === '6' || $core[0] === '7') {
      $url .= '/' . $name . '/' . $core;
    }
    else {
      $url .= '/' . $name . '/current';
    }
    return $url;
  }

}
