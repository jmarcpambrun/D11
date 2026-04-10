<?php

namespace Drupal\drd\Plugin\Action;

use Drupal\drd\Entity\BaseInterface as RemoteEntityInterface;
use Drupal\drd\Entity\DomainInterface;

/**
 * Provides a 'Database' action.
 *
 * @Action(
 *  id = "drd_action_database",
 *  label = @Translation("Download a database dump"),
 *  eca_version_introduced = "4.1.0",
 *  type = "drd_entity",
 * )
 */
class Database extends BaseEntityRemote {

  /**
   * {@inheritdoc}
   */
  public function executeAction(?RemoteEntityInterface $entity = NULL): bool|array|string {
    if ($entity instanceof DomainInterface) {
      $databases = parent::executeAction($entity);
      if (is_array($databases)) {
        foreach ($databases as $key => $targets) {
          foreach ($targets as $target => $def) {
            $tmpFile = $this->fileSystem->getTempDirectory() . DIRECTORY_SEPARATOR . implode('-', [
              'entity',
              $entity->id(),
              basename($def['file']),
            ]);
            if ($entity->download($def['file'], $tmpFile)) {
              $databases[$key][$target]['file'] = $tmpFile;
              $this->setOutput($tmpFile);
            }
            else {
              unset($databases[$key][$target]['file']);
            }
          }
        }
        if ($this->getOutput()) {
          return $databases;
        }
      }
    }
    return FALSE;
  }

}
