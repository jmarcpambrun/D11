<?php

namespace Drupal\drd\Plugin\views\field;

use Drupal\drd\Entity\DomainInterface;
use Drupal\views\Plugin\views\field\Standard;
use Drupal\views\ResultRow;

/**
 * A handler to display the latest ping status of a domain.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("drd_ping_status")
 */
class PingStatus extends Standard {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    $this->realField = 'id';
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    if (!empty($this->options['relationship']) && !empty($values->_relationship_entities[$this->options['relationship']])) {
      $domain = $values->_relationship_entities[$this->options['relationship']];
    }
    else {
      $domain = $values->_entity;
    }

    if (!($domain instanceof DomainInterface)) {
      return '';
    }

    $status = $domain->getLatestPingStatus(FALSE);
    if ($status === NULL) {
      return $this->t('unknown');
    }

    if ($status) {
      return $this->t('ok');
    }

    return $this->t('failed');
  }

}
