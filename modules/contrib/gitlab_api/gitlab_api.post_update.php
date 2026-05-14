<?php

/**
 * @file
 * Post-update hooks for gitlab_api.
 */

declare(strict_types=1);

use Drupal\gitlab_api\Entity\GitlabServer;

/**
 * Backfill GitlabServer.label from the URL when missing.
 *
 * Earlier revisions used `url` as the entity label. This change makes `label`
 * a real, separately-stored field; copy the URL into label for any server
 * that has none.
 */
function gitlab_api_post_update_backfill_server_label(): void {
  /** @var \Drupal\gitlab_api\Entity\GitlabServer $server */
  foreach (GitlabServer::loadMultiple() as $server) {
    if ((string) $server->label() === '') {
      $server->set('label', $server->getUrl());
      $server->save();
    }
  }
}
