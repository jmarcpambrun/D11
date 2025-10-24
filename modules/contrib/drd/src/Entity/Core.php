<?php

namespace Drupal\drd\Entity;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Cache\UseCacheBackendTrait;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\drd\Update\PluginStorageInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Core entity.
 *
 * @ingroup drd
 *
 * @ContentEntityType(
 *   id = "drd_core",
 *   label = @Translation("Core"),
 *   handlers = {
 *     "view_builder" = "Drupal\drd\Entity\ViewBuilder\Core",
 *     "list_builder" = "Drupal\drd\Entity\ListBuilder\Core",
 *     "views_data" = "Drupal\drd\Entity\ViewsData\Core",
 *
 *     "form" = {
 *       "default" = "Drupal\drd\Entity\Form\Core",
 *       "add" = "Drupal\drd\Entity\Form\Core",
 *       "edit" = "Drupal\drd\Entity\Form\Core",
 *       "delete" = "Drupal\drd\Entity\Form\CoreDelete",
 *     },
 *     "access" = "Drupal\drd\Entity\AccessControlHandler\Core",
 *   },
 *   base_table = "drd_core",
 *   admin_permission = "administer DrdCore entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *   },
 *   links = {
 *     "canonical" = "/drd/cores/core/{drd_core}",
 *     "edit-form" = "/drd/cores/core/{drd_core}/edit",
 *     "delete-form" = "/drd/cores/core/{drd_core}/delete"
 *   },
 *   field_ui_base_route = "drd_core.settings"
 * )
 */
class Core extends ContentEntityBase implements CoreInterface {

  use EntityChangedTrait;
  use UseCacheBackendTrait;
  use BaseFieldTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $values, string $entity_type, ?string $bundle = NULL) {
    parent::__construct($values, $entity_type, $bundle);
    $this->cacheBackend = \Drupal::cache();
  }

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values): void {
    parent::preCreate($storage, $values);
    $values += [
      'user_id' => \Drupal::currentUser()->id(),
      'host' => 1,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getName(bool $fallbackToDomain = TRUE): string {
    return $this->get('name')->value ?: '';
  }

  /**
   * {@inheritdoc}
   */
  public function setName(?string $name): BaseInterface {
    $this->set('name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getHost(): ?HostInterface {
    $entity = $this->get('host')->entity;
    return $entity instanceof HostInterface ? $entity : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setHost(HostInterface $host): CoreInterface {
    $this->set('host', $host->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDrupalRelease(): ?ReleaseInterface {
    $entity = $this->get('drupalversion')->entity;
    return $entity instanceof ReleaseInterface ? $entity : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setGitRepo(string $url): CoreInterface {
    $this->set('gitrepo', $url);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getGitRepo(): string {
    return $this->get('gitrepo')->value ?: '';
  }

  /**
   * {@inheritdoc}
   */
  public function getUpdateSettings(): array {
    $settings = $this->get('updsettings')->getValue();
    return empty($settings) ? [] : $settings[0];
  }

  /**
   * {@inheritdoc}
   */
  public function getUpdatePlugin(): PluginStorageInterface {
    /** @var \Drupal\drd\Update\ManagerStorageInterface $updateManager */
    $updateManager = \Drupal::service('plugin.manager.drd_update.storage');
    return $updateManager->executableInstance($this->getUpdateSettings());
  }

  /**
   * {@inheritdoc}
   */
  public function setDrupalRelease(ReleaseInterface $release): CoreInterface {
    $this->set('drupalversion', $release->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime(): int {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime(int $timestamp): BaseInterface {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner(): ?UserInterface {
    $entity = $this->get('user_id')->entity;
    return $entity instanceof UserInterface ? $entity : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId(): int|null {
    return (int) $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid): static {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account): static {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isPublished(): bool {
    return (bool) $this->get('status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setPublished(bool $published): BaseInterface {
    $this->set('status', $published ? NodeInterface::PUBLISHED : NodeInterface::NOT_PUBLISHED);
    if (!$published) {
      /** @var \Drupal\drd\Entity\DomainInterface $domain */
      foreach ($this->getDomains() as $domain) {
        $domain
          ->setPublished(FALSE)
          ->save();
      }
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getLangCode(): string {
    return $this->get('langcode')->value ?: '';
  }

  /**
   * {@inheritdoc}
   */
  public function getDrupalRoot(): string {
    return $this->get('drupalroot')->value ?: '';
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = [];
    self::idBaseFieldDefinitions($fields);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the Core entity.'))
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -7,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -7,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    self::metaBaseFieldDefinitions($fields);

    $fields['header'] = BaseFieldDefinition::create('key_value')
      ->setLabel(t('Header'))
      ->setDescription(t('Header key/value pairs for all domains in this core.'))
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setCustomStorage(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'key_value_textfield',
        'weight' => 0,
        'settings' => [
          'key_size' => 60,
          'key_placeholder' => 'Key',
          'size' => 60,
          'placeholder' => 'Value',
          'description_placeholder' => '',
          'description_enabled' => FALSE,
        ],
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['host'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Host'))
      ->setDescription(t('The host on which this core is installed.'))
      ->setSetting('target_type', 'drd_host')
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => -5,
        'settings' => [],
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'weight' => -5,
        'settings' => [
          'link' => TRUE,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['drupalroot'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Drupal root'))
      ->setDescription(t('The root directory of the Drupal installation.'))
      ->setRequired(TRUE)
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['drupalversion'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Drupal version'))
      ->setDescription(t('The installed Drupal core version.'))
      ->setSetting('target_type', 'drd_release')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'weight' => -3,
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['warnings'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Warnings'))
      ->setDescription(t('The requirements from the status page that cause a warning.'))
      ->setSetting('target_type', 'drd_requirement')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    $fields['errors'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Errors'))
      ->setDescription(t('The requirements from the status page that cause an error.'))
      ->setSetting('target_type', 'drd_requirement')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    $fields['updsettings'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Update Settings'))
      ->setDescription(t('Serialized settings for update.'))
      ->setDefaultValue([]);

    $fields['locked_releases'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Locked Releases'))
      ->setDescription(t('Locked releases for this core.'))
      ->setSetting('target_type', 'drd_release')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    $fields['hacked_releases'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Hacked Releases'))
      ->setDescription(t('Hacked releases for this core.'))
      ->setSetting('target_type', 'drd_release')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    $fields['gitrepo'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Git repository'))
      ->setDescription(t('The Git repository of the Drupal installation.'))
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getDomains(array $properties = []): array {
    $properties['core'] = $this->id();
    $domains = [];
    try {
      /** @var \Drupal\drd\Entity\DomainInterface[] $domains */
      $domains = \Drupal::entityTypeManager()->getStorage('drd_domain')->loadByProperties($properties);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      // Ignore.
    }
    return $domains;
  }

  /**
   * {@inheritdoc}
   */
  public function getFirstActiveDomain(): ?DomainInterface {
    $properties = [
      'core' => $this->id(),
      'installed' => 1,
    ];
    try {
      $storage = \Drupal::entityTypeManager()->getStorage('drd_domain');
      /** @var \Drupal\drd\Entity\DomainInterface[] $domains */
      $domains = $storage->loadByProperties($properties);
      return empty($domains) ? NULL : array_shift($domains);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      // Ignore.
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getHeader(): array {
    $host = $this->getHost();
    $headers = $host === NULL ? [] : $host->getHeader();
    foreach ($this->get('header') as $header) {
      if (property_exists($header, 'key') && property_exists($header, 'value')) {
        $headers[$header->key] = $header->value;
      }
    }
    return $headers;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableUpdates(bool $includeLocked = FALSE, bool $securityOnly = FALSE, bool $forceLockedSecurity = FALSE): array {
    $releases = [];
    $checked = [];
    foreach ($this->getDomains() as $domain) {
      foreach ($domain->getReleases() as $release) {
        if ($release->isUnsupported()) {
          // No supported release, skip that.
          continue;
        }
        if (($major = $release->getMajor()) && ($project = $major->getProject()) && !in_array($project->getName(), $checked, TRUE)) {
          $checked[] = $project->getName();
          if (!$major->isHidden()) {
            $recommended = $major->getRecommendedRelease();
            if ($recommended !== NULL && $recommended->id() !== $release->id()) {
              $parent = $major->getParentProject();
              if ($parent === NULL) {
                if (!$includeLocked && $this->isReleaseLocked($release, TRUE) && !($forceLockedSecurity && $release->isSecurityRelevant())) {
                  continue;
                }
                if ($securityOnly && !$release->isSecurityRelevant()) {
                  continue;
                }
                $releases[] = $recommended;
              }
            }
          }
        }
      }
    }
    return $releases;
  }

  /**
   * Build a cache id.
   *
   * @param string $topic
   *   The topic for the cache id.
   *
   * @return string
   *   The cache id.
   */
  private function cid(string $topic): string {
    return implode(':', ['drd_core', $this->uuid(), $topic]);
  }

  /**
   * {@inheritdoc}
   */
  public function getUpdateLogList(): array {
    $logList = $this->cacheGet($this->cid('updatelogs'));
    return $logList->data ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getUpdateLog(int $timestamp): string {
    $cid = $this->cid('updatelog:' . $timestamp);
    $log = $this->cacheGet($cid);
    return $log->data ?? 'No longer available';
  }

  /**
   * {@inheritdoc}
   */
  public function saveUpdateLog(string $log): CoreInterface {
    $logList = $this->getUpdateLogList();
    $timestamp = \Drupal::time()->getRequestTime();
    $cid = $this->cid('updatelog:' . $timestamp);
    $this->cacheSet($cid, $log);
    $logList[] = [
      'timestamp' => $timestamp,
      'cid' => $cid,
    ];
    $this->cacheSet($this->cid('updatelogs'), $logList);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setLockedReleases(array $releases): CoreInterface {
    $this->set('locked_releases', $releases);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getLockedReleases(): array {
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $releases */
    $releases = $this->get('locked_releases');
    /** @var \Drupal\drd\Entity\ReleaseInterface[] $lockedReleases */
    $lockedReleases = $releases->referencedEntities();
    return $lockedReleases;
  }

  /**
   * {@inheritdoc}
   */
  public function isReleaseLocked(ReleaseInterface $release, bool $checkGlobal = FALSE): bool {
    return ($checkGlobal && $release->isLocked()) || in_array($release, $this->getLockedReleases(), TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function lockRelease(ReleaseInterface $release): CoreInterface {
    if (!$this->isReleaseLocked($release)) {
      $locked_releases = $this->getLockedReleases();
      $locked_releases[] = $release;
      $this->setLockedReleases($locked_releases);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function unlockRelease(ReleaseInterface $release): CoreInterface {
    if ($this->isReleaseLocked($release)) {
      $locked_releases = $this->getLockedReleases();
      $idx = array_search($release, $locked_releases, TRUE);
      unset($locked_releases[$idx]);
      $this->setLockedReleases($locked_releases);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function unlockAllReleases(): CoreInterface {
    $this->setLockedReleases([]);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setHackedReleases(array $releases): CoreInterface {
    $this->set('hacked_releases', $releases);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getHackedReleases(): array {
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $releases */
    $releases = $this->get('hacked_releases');
    /** @var \Drupal\drd\Entity\ReleaseInterface[] $lockedReleases */
    $lockedReleases = $releases->referencedEntities();
    return $lockedReleases;
  }

  /**
   * {@inheritdoc}
   */
  public function isReleaseHacked(ReleaseInterface $release): bool {
    return in_array($release, $this->getHackedReleases(), TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function markReleaseHacked(ReleaseInterface $release): CoreInterface {
    if (!$this->isReleaseHacked($release)) {
      $hacked_releases = $this->getHackedReleases();
      $hacked_releases[] = $release;
      $this->setHackedReleases($hacked_releases);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function markReleaseUnhacked(ReleaseInterface $release): CoreInterface {
    if ($this->isReleaseHacked($release)) {
      $hacked_releases = $this->getHackedReleases();
      $idx = array_search($release, $hacked_releases, TRUE);
      unset($hacked_releases[$idx]);
      $this->setHackedReleases($hacked_releases);
    }
    return $this;
  }

}
