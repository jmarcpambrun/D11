<?php

namespace Drupal\drd\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\drd\Entity\BaseInterface as EntityBaseInterface;

/**
 * Base class for DRD Action plugins.
 */
abstract class BaseEntity extends Base implements BaseEntityInterface {

  /**
   * DRD entity.
   *
   * @var \Drupal\drd\Entity\BaseInterface|null
   */
  protected ?EntityBaseInterface $drdEntity = NULL;

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE): bool|AccessResultInterface {
    if (!isset($object)) {
      // This is a general check if we have access to the action itself.
      return parent::access($object, $account, $return_as_object);
    }

    $this->drdEntity = $object;
    if (parent::access($object, $account)) {
      return $this->drdEntity->access('edit', $account, $return_as_object);
    }
    return $return_as_object ? AccessResult::forbidden() : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function log(string $severity, string $message, array $args = []): void {
    if (empty($this->drdEntity) || $this->drdEntity->isNew()) {
      return;
    }
    $args['@entity_id'] = $this->drdEntity->id();
    $args['@entity_name'] = $this->drdEntity->label();
    $args['@entity_type'] = $this->drdEntity->getEntityTypeId();
    /* @noinspection PhpUnhandledExceptionInspection */
    $args['link'] = $this->drdEntity->toUrl()->toString(TRUE)->getGeneratedUrl();
    parent::log($severity, $message, $args);
  }

}
