<?php

namespace Drupal\drd\Plugin\Update\Process;

use Drupal\drd\Update\PluginProcessInterface;
use Drupal\drd\Update\PluginStorageInterface;

/**
 * Provides LakeDrops update process plugin.
 *
 * This works for projects that have been built with the LakeDrops D8 project
 * template and of course others that follow a similar workflow.
 *
 * @see https://gitlab.lakedrops.com/lakedrops/d8-project
 *
 * @Update(
 *  id = "lakedrops_d8",
 *  admin_label = @Translation("LakeDrops Drupal 8"),
 * )
 */
class LakeDropsD8 extends Base {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'pulldb' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  protected function requiresDatabase(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function process(PluginStorageInterface $storage): PluginProcessInterface {
    parent::process($storage);

    foreach ($this->domains as $domain) {
      // Execute some drush commands (cr, updatedb, cex)
      $storage->log('Process domain id ' . $domain->id());
      foreach (['cache-rebuild',
        'updatedb',
        'config-export sync',
      ] as $command) {
        $storage->log('Drush command ' . $command);
        if ($this->shell($storage, '../vendor/drush/drush/drush -y --uri=' . $domain->getLocalUrl() . ' ' . $command, $storage->getDrupalDirectory())) {
          throw new \RuntimeException('Drush command failed.');
        }
      }
    }

    $this->succeeded = TRUE;
    return $this;
  }

}
