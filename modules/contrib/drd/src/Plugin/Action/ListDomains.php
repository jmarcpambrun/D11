<?php

namespace Drupal\drd\Plugin\Action;

/**
 * Provides a 'ListDomains' action.
 *
 * @Action(
 *  id = "drd_action_list_domains",
 *  label = @Translation("List Domains"),
 *  eca_version_introduced = "4.1.0",
 *  type = "drd",
 * )
 */
class ListDomains extends ListEntities {

  /**
   * {@inheritdoc}
   */
  public function executeAction(): mixed {
    $rows = [];

    /** @var \Drupal\drd\Entity\DomainInterface $domain */
    foreach ($this->prepareSelection()->domains() as $domain) {
      $rows[] = [
        'domain-id' => $domain->id(),
        'domain-label' => $domain->label(),
        'domain' => $domain->getDomainName(),
        'core-id' => $domain->getCore()->id(),
        'core-label' => $domain->getCore()->label(),
        'host-id' => $domain->getCore()->getHost()->id(),
        'host-label' => $domain->getCore()->getHost()->label(),
      ];
    }
    return $rows;
  }

}
