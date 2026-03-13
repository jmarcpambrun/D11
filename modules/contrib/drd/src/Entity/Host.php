<?php

namespace Drupal\drd\Entity;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Host entity.
 *
 * @ingroup drd
 *
 * @ContentEntityType(
 *   id = "drd_host",
 *   label = @Translation("Host"),
 *   handlers = {
 *     "view_builder" = "Drupal\drd\Entity\ViewBuilder\Host",
 *     "list_builder" = "Drupal\drd\Entity\ListBuilder\Host",
 *     "views_data" = "Drupal\drd\Entity\ViewsData\Host",
 *
 *     "form" = {
 *       "default" = "Drupal\drd\Entity\Form\Host",
 *       "add" = "Drupal\drd\Entity\Form\Host",
 *       "edit" = "Drupal\drd\Entity\Form\Host",
 *       "delete" = "Drupal\drd\Entity\Form\HostDelete",
 *     },
 *     "access" = "Drupal\drd\Entity\AccessControlHandler\Host",
 *   },
 *   base_table = "drd_host",
 *   admin_permission = "administer DrdHost entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *   },
 *   links = {
 *     "canonical" = "/drd/hosts/host/{drd_host}",
 *     "edit-form" = "/drd/hosts/host/{drd_host}/edit",
 *     "delete-form" = "/drd/hosts/host/{drd_host}/delete"
 *   },
 *   field_ui_base_route = "drd_host.settings"
 * )
 */
class Host extends ContentEntityBase implements HostInterface {
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
  public function getEncryptedFieldNames(): array {
    return [
      'ssh2setting',
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
  public function setPublished(bool $published): BaseInterface {
    $this->set('status', $published ? NodeInterface::PUBLISHED : NodeInterface::NOT_PUBLISHED);
    if (!$published) {
      /** @var \Drupal\drd\Entity\CoreInterface $core */
      foreach ($this->getCores() as $core) {
        $core
          ->setPublished(FALSE)
          ->save();
      }
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDrush(): string {
    return $this->get('drush')->value ?: '';
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

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the Host entity.'))
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
      ->setDescription(t('Header key/value pairs for all domains in all cores of this host.'))
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

    $fields['ssh2'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Enable SSH2'))
      ->setDescription(t('A boolean indicating whether the remote host is supporting SSH2.'))
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => -4,
        'settings' => [
          'display_label' => TRUE,
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['ssh2setting'] = BaseFieldDefinition::create('map')
      ->setLabel(t('SSH2 Settings'))
      ->setDescription(t('Serialized settings for SSH2 connectivity.'))
      ->setDefaultValue([]);

    $fields['drush'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Drush executable'))
      ->setDescription(t('Full path to the Drush executable on the remote host.'))
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'string',
        'weight' => -3,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['ipv4'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('IP v4'))
      ->setDescription(t('The v4 IP addresses for this host.'))
      ->setSetting('size', 'big')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'ipv4field_formatter',
        'weight' => -6,
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['ipv6'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('IP v6'))
      ->setDescription(t('The v6 IP addresses for this host.'))
      ->setSetting('size', 'big')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'ipv6field_formatter',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getCores(array $properties = []): array {
    $properties['host'] = $this->id();
    try {
      /** @var \Drupal\drd\Entity\CoreInterface[] $cores */
      $cores = \Drupal::entityTypeManager()->getStorage('drd_core')->loadByProperties($properties);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $cores = [];
    }
    return $cores;
  }

  /**
   * {@inheritdoc}
   */
  public function getDomains(array $properties = []): array {
    $properties['core'] = array_keys($this->getCores());
    if (empty($properties['core'])) {
      return [];
    }
    try {
      /** @var \Drupal\drd\Entity\DomainInterface[] $domains */
      $domains = \Drupal::entityTypeManager()->getStorage('drd_domain')->loadByProperties($properties);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $domains = [];
    }
    return $domains;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsSsh(): bool {
    return (bool) $this->get('ssh2')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getSshSettings(): array {
    $settings = $this->get('ssh2setting')->getValue();
    $settings = empty($settings) ? [] : $settings[0];
    $settings += [
      'host' => '',
      'port' => 22,
      'auth' => [
        'mode' => 1,
        'username' => '',
        'password' => '',
        'file_public_key' => '',
        'file_private_key' => '',
        'key_secret' => '',
      ],
    ];
    /** @var \Drupal\drd\Encryption $service */
    $service = \Drupal::service('drd.encrypt');
    $service->decrypt($settings);
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function setSshSettings(array $settings): HostInterface {
    /** @var \Drupal\drd\Encryption $service */
    $service = \Drupal::service('drd.encrypt');
    $service->encrypt($settings);
    $this->set('ssh2setting', $settings);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getIpv4(bool $refresh = TRUE): string|bool {
    $ipv4 = $this->get('ipv4')->getValue();
    if (empty($ipv4[0]['value'])) {
      if ($refresh) {
        return $this->getIpv4(FALSE);
      }
      return FALSE;
    }
    return long2ip($ipv4[0]['value']);
  }

  /**
   * {@inheritdoc}
   */
  public function updateIpAddresses(): HostInterface {
    $ipv4 = $ipv6 = [];
    /** @var CoreInterface $core */
    foreach ($this->getCores() as $core) {
      foreach ($core->getDomains() as $domain) {
        \Drupal::service('drd.dnslookup')->lookup($domain->getDomainName(), $ipv4, $ipv6);
      }
    }
    $this->set('ipv4', array_unique($ipv4));
    $this->set('ipv6', array_unique($ipv6));
    try {
      $this->save();
    }
    catch (EntityStorageException) {
      // Ignore.
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function findOrCreateByHost(string $name): static {
    $ipv4 = $ipv6 = [];
    \Drupal::service('drd.dnslookup')->lookup($name, $ipv4, $ipv6);
    /* @noinspection PhpUnhandledExceptionInspection */
    $storage = \Drupal::entityTypeManager()->getStorage('drd_host');
    if (!empty($ipv4)) {
      $hosts = $storage->loadByProperties([
        'ipv4' => $ipv4,
      ]);
    }
    if (empty($hosts)) {
      /** @var \Drupal\drd\Entity\Host $host */
      $host = $storage->create([
        'name' => $name,
        'ipv4' => $ipv4,
        'ipv6' => $ipv6,
      ]);
      /* @noinspection PhpUnhandledExceptionInspection */
      $host->save();
    }
    else {
      /** @var \Drupal\drd\Entity\Host $host */
      $host = reset($hosts);
    }
    // @phpstan-ignore-next-line
    return $host;
  }

  /**
   * {@inheritdoc}
   */
  public function getHeader(): array {
    $headers = [];
    foreach ($this->get('header') as $header) {
      if (property_exists($header, 'key') && property_exists($header, 'value')) {
        $headers[$header->key] = $header->value;
      }
    }
    return $headers;
  }

}
