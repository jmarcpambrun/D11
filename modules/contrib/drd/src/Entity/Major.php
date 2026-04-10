<?php

namespace Drupal\drd\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Major Version entity.
 *
 * @ingroup drd
 *
 * @ContentEntityType(
 *   id = "drd_major",
 *   label = @Translation("Major Version"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\drd\Entity\ListBuilderMajor",
 *     "views_data" = "Drupal\drd\Entity\ViewsData\Major",
 *
 *     "form" = {
 *       "default" = "Drupal\drd\Entity\Form\Major",
 *       "edit" = "Drupal\drd\Entity\Form\Major",
 *     },
 *     "access" = "Drupal\drd\Entity\AccessControlHandler\Major",
 *   },
 *   base_table = "drd_major",
 *   admin_permission = "administer DrdMajor entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "coreversion",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *   },
 *   links = {
 *     "canonical" = "/drd/majors/major/{drd_major}",
 *     "edit-form" = "/drd/majors/major/{drd_major}/edit",
 *     "delete-form" = "/drd/majors/major/{drd_major}/delete"
 *   },
 *   field_ui_base_route = "drd_major.settings"
 * )
 */
class Major extends ContentEntityBase implements MajorInterface {
  use EntityChangedTrait;
  use BaseFieldTrait;

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
  public function getName(): string {
    return $this->get('name')->value ?: '';
  }

  /**
   * {@inheritdoc}
   */
  public function setName(string $name): MajorInterface {
    $this->set('name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCoreVersion(): int {
    return $this->get('coreversion')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCoreVersion(int $coreversion): MajorInterface {
    $this->set('coreversion', $coreversion);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getMajorVersion(): int {
    return $this->get('majorversion')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setMajorVersion(int $majorversion): MajorInterface {
    $this->set('majorversion', $majorversion);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getProject(): ?ProjectInterface {
    $entity = $this->get('project')->entity;
    return $entity instanceof ProjectInterface ? $entity : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setProject(ProjectInterface $project): MajorInterface {
    $this->set('project', $project->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getParentProject(): ?ProjectInterface {
    $entity = $this->get('parentproject')->entity;
    return $entity instanceof ProjectInterface ? $entity : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setParentProject(ProjectInterface $project): MajorInterface {
    $this->set('parentproject', $project->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRecommendedRelease(): ?ReleaseInterface {
    $entity = $this->get('recommended')->entity;
    return $entity instanceof ReleaseInterface ? $entity : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setRecommendedRelease(ReleaseInterface $release): MajorInterface {
    $this->set('recommended', $release->id());
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
  public function setCreatedTime(int $timestamp): MajorInterface {
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
  public function setPublished(bool $published): MajorInterface {
    $this->set('status', $published ? NodeInterface::PUBLISHED : NodeInterface::NOT_PUBLISHED);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isHidden(): bool {
    return (bool) $this->get('hidden')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setHidden(bool $hidden): MajorInterface {
    $this->set('hidden', $hidden ? 1 : 0);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isSupported(): bool {
    return (bool) $this->get('supported')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setSupported(bool $supported): MajorInterface {
    $this->set('supported', $supported ? 1 : 0);
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
    self::metaBaseFieldDefinitions($fields);

    $fields['project'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Project'))
      ->setDescription(t('The project for which this is a major version.'))
      ->setSetting('target_type', 'drd_project')
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'weight' => -5,
        'settings' => [
          'link' => TRUE,
        ],
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['parentproject'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Parent project'))
      ->setDescription(t('The parent project in which this major versions project is included.'))
      ->setSetting('target_type', 'drd_project')
      ->setRequired(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'weight' => -4,
        'settings' => [
          'link' => TRUE,
        ],
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['coreversion'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Core version'))
      ->setDescription(t('The main core version.'))
      ->setSetting('size', 'small')
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['majorversion'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Major version'))
      ->setDescription(t('The major version within the core version.'))
      ->setSetting('size', 'small')
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['hidden'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Hidden'))
      ->setDescription(t('A boolean indicating whether the Major Version is hidden.'))
      ->setDefaultValue(FALSE);

    $fields['supported'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Supported'))
      ->setDescription(t('A boolean indicating whether the Major Version is supported.'))
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'weight' => -3,
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['recommended'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Recommended release'))
      ->setDescription(t('The recommended relase for this major version.'))
      ->setSetting('target_type', 'drd_release')
      ->setRequired(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'weight' => -2,
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['information'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Information'))
      ->setDescription(t('Serialized information about the release.'))
      ->setDefaultValue([]);

    $fields['updatestatus'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Update status'))
      ->setDescription(t('Aggregated update status of all release of this major.'))
      ->setSettings([
        'max_length' => 20,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function updateStatus(): MajorInterface {
    $query = \Drupal::database()->select('drd_domain__releases', 'd');
    $query->join('drd_release', 'r', 'd.releases_target_id=r.id');
    $query
      ->fields('r', ['updatestatus'])
      ->condition('r.major', $this->id());
    $stati = $query
      ->distinct()
      ->execute()
      ->fetchAllKeyed(0, 0);
    $this->set('updatestatus', implode(',', $stati));
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function findOrCreate(string $type, string $name, string $version): MajorInterface {
    $major = self::find($name, $version);
    if (empty($major)) {
      $project = Project::findOrCreate($type, $name);
      /* @noinspection PhpUnhandledExceptionInspection */
      $storage = \Drupal::entityTypeManager()->getStorage('drd_major');
      [$coreversion, $majorversion] = self::getVersionParts($version);
      /** @var \Drupal\drd\Entity\MajorInterface $major */
      $major = $storage->create([
        'project' => $project->id(),
        'coreversion' => $coreversion,
        'majorversion' => $majorversion,
      ]);
      /* @noinspection PhpUnhandledExceptionInspection */
      $major->save();
    }
    return $major;
  }

  /**
   * {@inheritdoc}
   */
  public static function find(string $name, string $version): bool|MajorInterface {
    $project = Project::find($name);
    if ($project) {
      /* @noinspection PhpUnhandledExceptionInspection */
      $storage = \Drupal::entityTypeManager()->getStorage('drd_major');
      [$coreversion, $majorversion] = self::getVersionParts($version);
      $majors = $storage->loadByProperties([
        'project' => $project->id(),
        'coreversion' => $coreversion,
        'majorversion' => $majorversion,
      ]);
    }
    return empty($majors) ? FALSE : reset($majors);
  }

  /**
   * Breaks a version number into pieces.
   *
   * @param string $version
   *   The version number.
   *
   * @return array
   *   The pieces of the version number.
   */
  protected static function getVersionParts(string $version): array {
    $parts = explode('-', $version);
    $coreparts = explode('.', $parts[0]);
    if (count($coreparts) === 3) {
      // This is semantic versioning.
      // @see https://www.drupal.org/project/drd/issues/3250994
      $coreversion = 8;
      $majorversion = (int) $coreparts[0];
    }
    else {
      $coreversion = (int) $coreparts[0];
      // Make sure we get a reasonable core version number.
      // @see https://gitlab.com/drupalspoons/drd/-/issues/66
      if ($coreversion > 100 || $coreversion < 1) {
        $coreversion = 8;
      }
      if (!empty($parts[1]) && count($coreparts) === 2) {
        [$majorversion] = explode('.', $parts[1]);
      }
      else {
        $majorversion = $coreversion;
      }
    }
    return [$coreversion, $majorversion];
  }

}
