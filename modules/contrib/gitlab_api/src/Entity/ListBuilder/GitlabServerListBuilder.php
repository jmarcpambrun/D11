<?php

namespace Drupal\gitlab_api\Entity\ListBuilder;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of gitlab servers.
 */
class GitlabServerListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['label'] = $this->t('Server URL');
    $header['id'] = $this->t('Machine name');
    $header['status'] = $this->t('Status');
    $header['default_server'] = $this->t('Default');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\gitlab_api\Entity\GitlabServer $entity */
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    $row['status'] = $entity->status() ? $this->t('Enabled') : $this->t('Disabled');
    $row['default_server'] = $entity->isDefault() ? $this->t('Yes') : '';
    return $row + parent::buildRow($entity);
  }

}
