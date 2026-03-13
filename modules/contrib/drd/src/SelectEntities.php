<?php

namespace Drupal\drd;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\drd\Entity\Core;
use Drupal\drd\Entity\CoreInterface;
use Drupal\drd\Entity\Domain;
use Drupal\drd\Entity\Host;

/**
 * Query for DRD entities.
 */
class SelectEntities implements SelectEntitiesInterface {

  use MessengerTrait;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * DRD entity type id.
   *
   * @var string
   */
  protected string $type;

  /**
   * Properties for selection.
   *
   * @var array
   */
  protected array $properties = [];

  /**
   * DRD host entity id.
   *
   * @var int|null
   */
  protected ?int $hostId = NULL;

  /**
   * DRD core entity id.
   *
   * @var int|null
   */
  protected ?int $coreId = NULL;

  /**
   * DRD domain entity id.
   *
   * @var int|null
   */
  protected ?int $domainId = NULL;

  /**
   * Tag id.
   *
   * @var int|null
   */
  protected ?int $tagId = NULL;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * Construct the Entity object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Connection $database) {
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public function getSelectionCriteria(): array {
    $result = [];
    foreach ([
      'tag' => $this->tagId,
      'host-id' => $this->hostId,
      'core-id' => $this->coreId,
      'domain-id' => $this->domainId,
    ] as $key => $value) {
      if (!empty($value)) {
        $result[$key] = $value;
      }
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function setTag(?string $name): SelectEntitiesInterface {
    if (!empty($name)) {
      try {
        $terms = $this->entityTypeManager->getStorage('taxonomy_term')
          ->loadByProperties(['name' => $name]);
      }
      catch (InvalidPluginDefinitionException | PluginNotFoundException) {
        $terms = [];
      }
      if (!empty($terms)) {
        $this->tagId = reset($terms)->id();
      }
      else {
        $this->tagId = -1;
      }
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setHost(?string $name): SelectEntitiesInterface {
    if (!empty($name)) {
      /* @noinspection PhpUnhandledExceptionInspection */
      $hosts = $this->entityTypeManager->getStorage('drd_host')
        ->loadByProperties([
          'name' => $name,
        ]);
      if (!empty($hosts)) {
        $this->hostId = reset($hosts)->id();
      }
      else {
        $this->hostId = -1;
      }
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setHostId(?int $id): SelectEntitiesInterface {
    if ($id !== NULL) {
      $this->hostId = $id;
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setCore(?string $name): SelectEntitiesInterface {
    if (!empty($name)) {
      /* @noinspection PhpUnhandledExceptionInspection */
      $cores = $this->entityTypeManager->getStorage('drd_core')
        ->loadByProperties([
          'name' => $name,
        ]);
      if (!empty($cores)) {
        $this->coreId = reset($cores)->id();
      }
      else {
        $this->coreId = -1;
      }
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setCoreId(?int $id): SelectEntitiesInterface {
    if ($id !== NULL) {
      $this->coreId = $id;
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setDomain(?string $domain): SelectEntitiesInterface {
    if (!empty($domain)) {
      /* @noinspection PhpUnhandledExceptionInspection */
      $domains = $this->entityTypeManager->getStorage('drd_domain')
        ->loadByProperties([
          'domain' => $domain,
        ]);
      if (!empty($domains)) {
        $this->domainId = reset($domains)->id();
      }
      else {
        $this->domainId = -1;
      }
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setDomainId(?int $id): SelectEntitiesInterface {
    if ($id !== NULL) {
      $this->domainId = $id;
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hosts(): array|bool {
    $this->type = 'host';
    $this->properties = [
      'status' => 1,
    ];
    if ($this->domainId) {
      /** @var \Drupal\drd\Entity\DomainInterface|null $domain */
      $domain = Domain::load($this->domainId);
      if ($domain && $core = $domain->getCore()) {
        $this->coreId = $core->id();
      }
      else {
        return $this->none();
      }
    }
    if ($this->coreId) {
      /** @var \Drupal\drd\Entity\CoreInterface|null $core */
      if (($core = Core::load($this->coreId)) && $host = $core->getHost()) {
        $this->hostId = $host->id();
      }
      else {
        return $this->none();
      }
    }
    if ($this->hostId) {
      return $this->search($this->hostId);
    }
    return $this->search($this->hostIdsByTerm());
  }

  /**
   * {@inheritdoc}
   */
  public function cores(): array|bool {
    $this->type = 'core';
    $this->properties = [
      'status' => 1,
    ];
    if ($this->hostId) {
      $this->properties['host'] = $this->hostId;
    }
    else {
      if ($this->domainId) {
        /** @var \Drupal\drd\Entity\DomainInterface|null $domain */
        $domain = Domain::load($this->domainId);
        if ($domain && $core = $domain->getCore()) {
          $this->coreId = $core->id();
        }
        else {
          return $this->none();
        }
      }
      if ($this->coreId) {
        return $this->search($this->coreId);
      }
    }
    return $this->search($this->coreIdsByTerm());
  }

  /**
   * {@inheritdoc}
   */
  public function domains(): array|bool {
    $this->type = 'domain';
    $this->properties = [
      'status' => 1,
      'installed' => 1,
    ];
    if ($this->hostId) {
      /** @var \Drupal\drd\Entity\Host|null $host */
      $host = Host::load($this->hostId);
      if ($host) {
        $cores = $host->getCores();
        array_walk($cores, function (CoreInterface $core) {
          $this->properties['core'][] = $core->id();
        });
      }
      else {
        return $this->none();
      }
    }
    elseif ($this->coreId) {
      $this->properties['core'] = $this->coreId;
    }
    elseif ($this->domainId) {
      return $this->search($this->domainId);
    }
    return $this->search($this->domainIdsByTerm());
  }

  /**
   * Get a list of all host IDs by a taxonomy term.
   *
   * @return int[]|bool
   *   Array of host IDs or FALSE;
   */
  private function hostIdsByTerm(): array|bool {
    return $this->entityIdsByTerm('h');
  }

  /**
   * Get a list of all core IDs by a taxonomy term.
   *
   * @return int[]|bool
   *   Array of core IDs or FALSE;
   */
  private function coreIdsByTerm(): array|bool {
    return $this->entityIdsByTerm('c');
  }

  /**
   * Get a list of all domain IDs by a taxonomy term.
   *
   * @return int[]|bool
   *   Array of domain IDs or FALSE;
   */
  private function domainIdsByTerm(): array|bool {
    return $this->entityIdsByTerm('d');
  }

  /**
   * Get a list of entity IDs by a taxonomy term.
   *
   * @param string $alias
   *   The table alias c|h|d for host|core|domain.
   *
   * @return int[]|bool
   *   Array of entity IDs or FALSE;
   */
  private function entityIdsByTerm(string $alias): array|bool {
    if ($this->tagId) {
      $query = $this->database->select('drd_domain', 'd');
      $query->join('drd_core', 'c', 'd.core = c.id');
      $query->join('drd_host', 'h', 'c.host = h.id');
      $query->leftJoin('drd_domain__terms', 'dt', 'd.id = dt.entity_id');
      $query->leftJoin('drd_core__terms', 'ct', 'c.id = ct.entity_id');
      $query->leftJoin('drd_host__terms', 'ht', 'h.id = ht.entity_id');
      $ids = $query->orConditionGroup()
        ->condition('dt.terms_target_id', (string) $this->tagId)
        ->condition('ct.terms_target_id', (string) $this->tagId)
        ->condition('ht.terms_target_id', (string) $this->tagId);
      $query
        ->fields($alias, ['id'])
        ->condition($ids);
      /** @var \Drupal\Core\Database\StatementInterface $query */
      $query->execute();
      return $query->fetchCol();
    }
    return FALSE;
  }

  /**
   * Nothing found, output a message and return FALSE.
   *
   * @return false
   *   Retrun FALSE if no entity was found.
   */
  protected function none(): bool {
    $this->messenger()->addMessage('No ' . $this->type . ' found!', 'error');
    return FALSE;
  }

  /**
   * One entity found.
   *
   * @param int $id
   *   The id of the found entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Array containing one entity.
   */
  protected function oneEntity(int $id): array {
    $entity = NULL;
    try {
      $entity = $this->entityTypeManager
        ->getStorage('drd_' . $this->type)
        ->load($id);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      // @todo Log this exception.
    }
    return $entity === NULL ? [] : [$entity];

  }

  /**
   * Multiple entities found.
   *
   * @param bool|int|array $id
   *   ID of the entity to search or NULL to find all.
   *
   * @return false|\Drupal\Core\Entity\EntityInterface[]
   *   List of all found entities.
   */
  protected function search(bool|int|array $id): array|bool {
    if (is_scalar($id) && !empty($id)) {
      $entities = $this->oneEntity($id);
    }
    elseif (is_array($id) && empty($id)) {
      $entities = [];
    }
    else {
      if (is_array($id)) {
        $this->properties['id'] = $id;
      }
      try {
        $entities = $this->entityTypeManager
          ->getStorage('drd_' . $this->type)
          ->loadByProperties($this->properties);
      }
      catch (InvalidPluginDefinitionException | PluginNotFoundException) {
        // @todo Log this exception.
      }
    }

    if (empty($entities)) {
      return $this->none();
    }

    return $entities;
  }

}
