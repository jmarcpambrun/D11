<?php

namespace Drupal\drd\Plugin\Action;

/**
 * Provides a 'ListHosts' action.
 *
 * @Action(
 *  id = "drd_action_list_hosts",
 *  label = @Translation("List Hosts"),
 *  eca_version_introduced = "4.1.0",
 *  type = "drd",
 * )
 */
class ListHosts extends ListEntities {

  /**
   * {@inheritdoc}
   */
  public function executeAction(): mixed {
    $rows = [];

    /** @var \Drupal\drd\Entity\HostInterface $host */
    foreach ($this->prepareSelection()->hosts() as $host) {
      $rows[] = [
        'host-id' => $host->id(),
        'host-label' => $host->label(),
      ];
    }
    return $rows;
  }

}
