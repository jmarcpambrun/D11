<?php

namespace Drupal\drd\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\update\UpdateManagerInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Release entity.
 *
 * @ingroup drd
 *
 * @ContentEntityType(
 *   id = "drd_release",
 *   label = @Translation("Release"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\drd\Entity\ListBuilder\Release",
 *     "views_data" = "Drupal\drd\Entity\ViewsData\Release",
 *
 *     "form" = {
 *       "default" = "Drupal\drd\Entity\Form\Release",
 *       "edit" = "Drupal\drd\Entity\Form\Release",
 *     },
 *     "access" = "Drupal\drd\Entity\AccessControlHandler\Release",
 *   },
 *   base_table = "drd_release",
 *   admin_permission = "administer DrdRelease entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "version",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *   },
 *   links = {
 *     "canonical" = "/drd/releases/release/{drd_release}",
 *     "edit-form" = "/drd/releases/release/{drd_release}/edit",
 *   },
 *   field_ui_base_route = "drd_release.settings"
 * )
 */
class Release extends ContentEntityBase implements ReleaseInterface {
  use EntityChangedTrait;
  use BaseFieldTrait;

  /**
   * Indicator for the release, whether it was just created.
   *
   * @var bool
   */
  private bool $justCreated = FALSE;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values): void {
    parent::preCreate($storage, $values);
    $values += ['user_id' => \Drupal::currentUser()->id()];
  }

  /**
   * {@inheritdoc}
   */
  public function isJustCreated(?bool $flag = NULL): bool {
    if (isset($flag)) {
      $this->justCreated = $flag;
    }
    return $this->justCreated;
  }

  /**
   * {@inheritdoc}
   */
  public function isUnsupported(): bool {
    return in_array($this->getUpdateStatus(), [
      (string) UpdateManagerInterface::REVOKED,
      (string) UpdateManagerInterface::NOT_SUPPORTED,
    ], TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function isSecurityRelevant(): bool {
    return ($this->getUpdateStatus() === (string) UpdateManagerInterface::NOT_SECURE);
  }

  /**
   * {@inheritdoc}
   */
  public function getUpdateStatus(): string {
    return $this->get('updatestatus')->value ?: '';
  }

  /**
   * {@inheritdoc}
   */
  public function getVersion(): string {
    return $this->get('version')->value ?: '';
  }

  /**
   * {@inheritdoc}
   */
  public function getReleaseVersion(): string {
    $version = explode('-', $this->getVersion());
    if (strpos($version[0], '.x') > 0) {
      array_shift($version);
    }
    return implode('-', $version);
  }

  /**
   * {@inheritdoc}
   */
  public function setVersion(string $version): ReleaseInterface {
    $this->set('version', $version);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getMajor(): ?MajorInterface {
    $entity = $this->get('major')->entity;
    return $entity instanceof MajorInterface ? $entity : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setMajor(MajorInterface $major): ReleaseInterface {
    $this->set('major', $major->id());
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
  public function setCreatedTime(int $timestamp): ReleaseInterface {
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
  public function getOwnerId(): ?int {
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
  public function setPublished(bool $published): ReleaseInterface {
    $this->set('status', $published ? NodeInterface::PUBLISHED : NodeInterface::NOT_PUBLISHED);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isLocked(): bool {
    return (bool) $this->get('locked')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setLocked(bool $locked): ReleaseInterface {
    $this->set('locked', $locked ? 1 : 0);
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
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = [];
    self::idBaseFieldDefinitions($fields);

    $fields['version'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Version'))
      ->setDescription(t('The version of the Release entity.'))
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    self::metaBaseFieldDefinitions($fields);

    $fields['major'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Major version'))
      ->setDescription(t('The major version for which this is a specific release.'))
      ->setSetting('target_type', 'drd_major')
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'weight' => -3,
        'settings' => [
          'link' => TRUE,
        ],
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['updatestatus'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Update status'))
      ->setDescription(t('The update status of this release.'))
      ->setSetting('size', 'small')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'weight' => -2,
        'settings' => [
          'type' => 'number_unformatted',
        ],
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['information'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Information'))
      ->setDescription(t('Serialized information about the release.'))
      ->setDefaultValue([]);

    $fields['updateinfo'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Update information'))
      ->setDescription(t('Serialized information about the update information of this release.'))
      ->setDefaultValue([]);

    $fields['locked'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Locked'))
      ->setDescription(t('A boolean indicating whether the Release is locked.'))
      ->setDefaultValue(FALSE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public static function findOrCreate(string $type, string $name, string $version): ReleaseInterface {
    $release = self::find($name, $version);
    if (empty($release)) {
      $major = Major::findOrCreate($type, $name, $version);
      /* @noinspection PhpUnhandledExceptionInspection */
      $storage = \Drupal::entityTypeManager()->getStorage('drd_release');
      /** @var ReleaseInterface $release */
      $release = $storage->create([
        'version' => $version,
        'major' => $major->id(),
      ]);
      /* @noinspection PhpUnhandledExceptionInspection */
      $release->save();
      $release->isJustCreated(TRUE);
    }
    return $release;
  }

  /**
   * {@inheritdoc}
   */
  public static function find(string $name, string $version): bool|ReleaseInterface {
    $major = Major::find($name, $version);
    if ($major) {
      /* @noinspection PhpUnhandledExceptionInspection */
      $storage = \Drupal::entityTypeManager()->getStorage('drd_release');
      $releases = $storage->loadByProperties([
        'major' => $major->id(),
        'version' => $version,
      ]);
    }
    return empty($releases) ? FALSE : reset($releases);
  }

  /**
   * Get information from the release details.
   *
   * @param string|null $key
   *   Get a portion of the update information or all.
   *
   * @return mixed
   *   Return all or a portion of the release's update information or FALSE if
   *   the requested information doesn't exist.
   */
  private function getUpdateInfo(?string $key = NULL): mixed {
    $value = $this->get('updateinfo')->getValue()[0];
    if (isset($key)) {
      return $value[$key] ?? FALSE;
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getInformation(): mixed {
    return $this->get('information')->getValue()[0];
  }

  /**
   * {@inheritdoc}
   */
  public function getProjectType(): string {
    if (($major = $this->getMajor()) && ($project = $major->getProject())) {
      return $project->getType() ?: '';
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getProjectLink(): Url {
    $uri = $this->getUpdateInfo('link');
    if (empty($uri)) {
      if (($major = $this->getMajor()) && ($project = $major->getProject())) {
        $uri = $project->getProjectLink();
      }
      else {
        $uri = 'https://www.drupal.org';
      }
    }
    return Url::fromUri($uri);
  }

  /**
   * {@inheritdoc}
   */
  public function getReleaseLink(): Url {
    $releases = $this->getUpdateInfo('releases');
    if (empty($releases[$this->getVersion()])) {
      return $this->getProjectLink();
    }
    return Url::fromUri($releases[$this->getVersion()]['release_link']);
  }

  /**
   * {@inheritdoc}
   */
  public function getDownloadLink(): Url {
    $releases = $this->getUpdateInfo('releases');
    if (empty($releases[$this->getVersion()])) {
      return $this->getReleaseLink();
    }
    return Url::fromUri($releases[$this->getVersion()]['download_link']);
  }

}
