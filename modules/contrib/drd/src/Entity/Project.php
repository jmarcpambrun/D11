<?php

namespace Drupal\drd\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Project entity.
 *
 * @ingroup drd
 *
 * @ContentEntityType(
 *   id = "drd_project",
 *   label = @Translation("Project"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\drd\Entity\ListBuilder\Project",
 *     "views_data" = "Drupal\drd\Entity\ViewsData\Project",
 *
 *     "form" = {
 *       "default" = "Drupal\drd\Entity\Form\Project",
 *       "edit" = "Drupal\drd\Entity\Form\Project",
 *     },
 *     "access" = "Drupal\drd\Entity\AccessControlHandler\Project",
 *   },
 *   base_table = "drd_project",
 *   admin_permission = "administer DrdProject entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "name" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *   },
 *   links = {
 *     "canonical" = "/drd/projects/project/{drd_project}",
 *     "edit-form" = "/drd/projects/project/{drd_project}/edit",
 *   },
 *   field_ui_base_route = "drd_project.settings"
 * )
 */
class Project extends ContentEntityBase implements ProjectInterface {
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
  public function getLabel(): string {
    return $this->get('label')->value ?: '';
  }

  /**
   * {@inheritdoc}
   */
  public function setLabel(string $label): ProjectInterface {
    $this->set('label', $label);
    return $this;
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
  public function setName(string $name): ProjectInterface {
    $this->set('name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): string {
    return $this->get('type')->value ?: '';
  }

  /**
   * {@inheritdoc}
   */
  public function setType(string $type): ProjectInterface {
    $this->set('type', $type);
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
  public function setCreatedTime(int $timestamp): ProjectInterface {
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
  public function setPublished(bool $published): ProjectInterface {
    $this->set('status', $published ? NodeInterface::PUBLISHED : NodeInterface::NOT_PUBLISHED);
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

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Label'))
      ->setDescription(t('The display name of the Project entity.'))
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the Project entity.'))
      ->setSettings([
        'max_length' => 100,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => -3,
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    self::metaBaseFieldDefinitions($fields);

    $fields['type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Type'))
      ->setDescription(t('One of core, module or theme.'))
      ->setRequired(TRUE)
      ->setSettings([
        'max_length' => 15,
        'text_processing' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'weight' => -2,
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['information'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Information'))
      ->setDescription(t('Serialized information about the project.'))
      ->setDefaultValue([]);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getProjectLink(): Url {
    $uri = 'https://www.drupal.org/project/' . $this->getName();
    return Url::fromUri($uri);
  }

  /**
   * {@inheritdoc}
   */
  public static function findOrCreate(string $type, string $name): ProjectInterface {
    $project = self::find($name);
    if (empty($project)) {
      /* @noinspection PhpUnhandledExceptionInspection */
      $storage = \Drupal::entityTypeManager()->getStorage('drd_project');
      $name = strtolower($name);
      $type = strtolower($type);
      /** @var \Drupal\drd\Entity\ProjectInterface $project */
      $project = $storage->create([
        'label' => $name,
        'name' => $name,
        'type' => $type,
      ]);
      /* @noinspection PhpUnhandledExceptionInspection */
      $project->save();
    }
    return $project;
  }

  /**
   * {@inheritdoc}
   */
  public static function find(string $name): ProjectInterface|bool {
    $name = strtolower($name);
    /* @noinspection PhpUnhandledExceptionInspection */
    $storage = \Drupal::entityTypeManager()->getStorage('drd_project');
    $projects = $storage->loadByProperties([
      'name' => $name,
    ]);
    return empty($projects) ? FALSE : reset($projects);
  }

}
