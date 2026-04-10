<?php

namespace Drupal\drd\Plugin\Action;

use Drupal\drd\Entity\BaseInterface as RemoteEntityInterface;
use Drupal\drd\Entity\DomainInterface;
use Drupal\drd\HttpRequest;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Provides a 'Download' action.
 *
 * @Action(
 *  id = "drd_action_download",
 *  label = @Translation("Download a file"),
 *  eca_version_introduced = "4.1.0",
 *  type = "drd_domain",
 * )
 */
class Download extends BaseEntityRemote {

  /**
   * {@inheritdoc}
   */
  public function executeAction(?RemoteEntityInterface $entity = NULL): bool|array|string {
    if (!($entity instanceof DomainInterface)) {
      return FALSE;
    }
    $response = parent::executeAction($entity);
    if ($response && $this->responseHeaders['X-DRD-Encrypted'][0]) {
      $fs = new Filesystem();
      $newfile = $this->arguments['destination'] . '.openssl';
      if ($fs->exists($newfile)) {
        $fs->remove($newfile);
      }
      $fs->rename($this->arguments['destination'], $newfile);
      $this->crypt->decryptFile($newfile);
    }
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  protected function setRequestOptions(HttpRequest $request): void {
    $request->setOption('sink', $this->arguments['destination']);
  }

  /**
   * {@inheritdoc}
   */
  protected function processResponse(): bool {
    return FALSE;
  }

}
