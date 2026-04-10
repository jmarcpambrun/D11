<?php

namespace Drupal\drd\Plugin\Action;

use Drupal\drd\Entity\BaseInterface as RemoteEntityInterface;
use Drupal\drd\Entity\DomainInterface;

/**
 * Provides a 'Blocks' action.
 *
 * @Action(
 *  id = "drd_action_blocks",
 *  label = @Translation("List and render blocks"),
 *  eca_version_introduced = "4.1.0",
 *  type = "drd_domain",
 * )
 */
class Blocks extends BaseEntityRemote {

  /**
   * {@inheritdoc}
   */
  public function executeAction(?RemoteEntityInterface $entity = NULL): bool|array|string {
    if (!($entity instanceof DomainInterface)) {
      return FALSE;
    }
    $response = parent::executeAction($entity);
    if (is_array($response)) {
      if (empty($this->arguments['module'])) {
        $site_config = $this->configFactory->getEditable('drd.general');
        $blocks = $site_config->get('remote_blocks');
        foreach ($response as $module => $items) {
          foreach ($items as $delta => $label) {
            $blocks[$module][$delta] = $label;
          }
        }
        $site_config
          ->set('remote_blocks', $blocks)
          ->save();
      }
      else {
        $entity->cacheBlock(
          $this->arguments['module'],
          $this->arguments['delta'],
          $response['data']
        );
      }
      return TRUE;
    }
    return FALSE;
  }

}
