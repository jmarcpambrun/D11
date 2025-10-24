<?php

namespace Drupal\drd\Entity;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\UseCacheBackendTrait;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\GeneratedLink;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\drd\ActionManagerInterface;
use Drupal\drd\Crypt\Base as CryptBase;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Domain entity.
 *
 * @ingroup drd
 *
 * @ContentEntityType(
 *   id = "drd_domain",
 *   label = @Translation("Domain"),
 *   handlers = {
 *     "view_builder" = "Drupal\drd\Entity\ViewBuilder\Domain",
 *     "list_builder" = "Drupal\drd\Entity\ListBuilder\Domain",
 *     "views_data" = "Drupal\drd\Entity\ViewsData\Domain",
 *
 *     "form" = {
 *       "default" = "Drupal\drd\Entity\Form\Domain",
 *       "edit" = "Drupal\drd\Entity\Form\Domain",
 *       "reset" = "Drupal\drd\Entity\Form\DomainReset",
 *     },
 *     "access" = "Drupal\drd\Entity\AccessControlHandler\Domain",
 *   },
 *   base_table = "drd_domain",
 *   admin_permission = "administer DrdDomain entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *   },
 *   links = {
 *     "canonical" = "/drd/domains/domain/{drd_domain}",
 *     "edit-form" = "/drd/domains/domain/{drd_domain}/edit",
 *     "reset-form" = "/drd/domains/domain/{drd_domain}/reset",
 *   },
 *   field_ui_base_route = "drd_domain.settings"
 * )
 */
class Domain extends ContentEntityBase implements DomainInterface {
  use EntityChangedTrait;
  use MessengerTrait;
  use UseCacheBackendTrait;
  use StringTranslationTrait;
  use BaseFieldTrait;

  // @todo Drupal 6 and 7 default to FALSE, Drupal 8 only supports TRUE.
  /**
   * Flag to determine if remote domain support clean URLs or not.
   *
   * @var bool
   */
  protected bool $cleanUrl = TRUE;

  /**
   * The DRD action manager.
   *
   * @var \Drupal\drd\ActionManagerInterface
   */
  protected ActionManagerInterface $actionManager;

  /**
   * The remote database declarations.
   *
   * @var array
   */
  private array $databases = [];

  /**
   * {@inheritdoc}
   */
  public function __construct(array $values, string $entity_type, ?string $bundle = NULL) {
    parent::__construct($values, $entity_type, $bundle);
    $this->cacheBackend = \Drupal::cache();
    $this->actionManager = \Drupal::service('plugin.manager.drd_action');
  }

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
      'authsetting',
      'cryptsetting',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCookies(): array {
    $cache = $this->cacheGet($this->cid('cookies'));
    if ($cache) {
      return $cache->data;
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function setCookies(array $cookies): DomainInterface {
    $this->cacheSet($this->cid('cookies'), $cookies);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getName(bool $fallbackToDomain = TRUE): string {
    $name = $this->get('name')->value ?: '';
    return (empty($name) && $fallbackToDomain) ? $this->getDomainName() : $name;
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
  public function getDomainName(): string {
    return $this->get('domain')->value ?: '';
  }

  /**
   * {@inheritdoc}
   */
  public function getCore(): ?CoreInterface {
    $entity = $this->get('core')->entity;
    return $entity instanceof CoreInterface ? $entity : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setCore(CoreInterface $core): DomainInterface {
    $this->set('core', $core->id());
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
  public function isInstalled(): bool {
    return (bool) $this->get('installed')->value;
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
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAuth(): string {
    return $this->get('auth')->value ?: '';
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthSetting(?string $type = NULL, bool $for_remote = FALSE): array {
    if (!isset($type)) {
      $type = $this->getAuth();
    }
    if (empty($type)) {
      return [];
    }

    if ($for_remote) {
      /** @var \Drupal\drd\Plugin\Auth\Base $auth */
      $auth = \Drupal::service('plugin.manager.drd_auth')->createInstance($type);
      if (!$auth->storeSettingRemotely()) {
        return [];
      }
    }
    $settings = $this->get('authsetting')->getValue();
    $settings = $settings[0][$type] ?? [];

    /** @var \Drupal\drd\Encryption $service */
    $service = \Drupal::service('drd.encrypt');
    $service->decrypt($settings);
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function setAuthSetting(array $settings): DomainInterface {
    /** @var \Drupal\drd\Encryption $service */
    $service = \Drupal::service('drd.encrypt');
    $service->encrypt($settings);
    $this->set('authsetting', $settings);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCrypt(): string {
    return $this->get('crypt')->value ?: '';
  }

  /**
   * {@inheritdoc}
   */
  public function getCryptSetting(?string $type = NULL): array {
    if (!isset($type)) {
      $type = $this->getCrypt();
    }
    if (empty($type)) {
      return [];
    }
    $settings = $this->get('cryptsetting')->getValue();
    $settings = $settings[0][$type] ?? [];

    /** @var \Drupal\drd\Encryption $service */
    $service = \Drupal::service('drd.encrypt');
    $service->decrypt($settings);
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function setCryptSetting(array $settings, $encrypted = FALSE): DomainInterface {
    if (!$encrypted) {
      /** @var \Drupal\drd\Encryption $service */
      $service = \Drupal::service('drd.encrypt');
      $service->encrypt($settings);
    }
    $this->set('cryptsetting', $settings);
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

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the Domain entity.'))
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -11,
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    self::metaBaseFieldDefinitions($fields);

    $fields['header'] = BaseFieldDefinition::create('key_value')
      ->setLabel(t('Header'))
      ->setDescription(t('Header key/value pairs for the domain.'))
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

    $fields['core'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Core'))
      ->setDescription(t('The Drupal core that contains that domain.'))
      ->setSetting('target_type', 'drd_core')
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'weight' => -10,
        'settings' => [
          'link' => TRUE,
        ],
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['releases'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Releases'))
      ->setDescription(t('The installed core, module and theme releases on this domain.'))
      ->setSetting('target_type', 'drd_release')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

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

    $fields['domain'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Domain'))
      ->setDescription(t('The domain name.'))
      ->setRequired(TRUE)
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'weight' => -7,
        'settings' => [],
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['aliase'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Aliase'))
      ->setDescription(t('The aliases for this domain name.'))
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'weight' => -4,
        'settings' => [],
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['uripath'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Uri Path'))
      ->setDescription(t('The base uri path.'))
      ->setRequired(TRUE)
      ->setDefaultValue('')
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'weight' => -6,
        'settings' => [],
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['port'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Port'))
      ->setDescription(t('The port to connect to if different from default ports.'))
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'weight' => -5,
        'settings' => [
          'type' => 'number_unformatted',
        ],
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['auth'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Authentication'))
      ->setDescription(t('The selected type of authenticaion.'))
      ->setRequired(TRUE)
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'weight' => -3,
        'settings' => [],
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['authsetting'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Authentication Settings'))
      ->setDescription(t('Serialized settings for authentication.'))
      ->setDefaultValue([]);

    $fields['crypt'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Encryption'))
      ->setDescription(t('The selected type of encryption.'))
      ->setRequired(TRUE)
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'weight' => -2,
        'settings' => [],
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['cryptsetting'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Encryption Settings'))
      ->setDescription(t('Serialized settings for encryption.'))
      ->setDefaultValue([]);

    $fields['installed'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Installed'))
      ->setDescription(t('A boolean indicating whether the DRD module is installed remotely.'))
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'weight' => -9,
        'settings' => [],
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['secure'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Secure connection'))
      ->setDescription(t('A boolean indicating whether SSL is supported.'))
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'weight' => -8,
        'settings' => ['format' => 'ssl-yes-no'],
        'type' => 'drd_domain_secure',
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  /**
   * Determine if URI uses SSL and port to be used.
   *
   * @param array $uri_parts
   *   The parsed URL as an array of parts.
   *
   * @return array
   *   An array with 2 values: TRUE or FALSE to tell if URL uses SSL and the
   *   port to be used.
   */
  private static function calculateScheme(array $uri_parts): array {
    $secure = ($uri_parts['scheme'] === 'https');
    if (!empty($uri_parts['port'])) {
      $port = $uri_parts['port'];
    }
    else {
      $port = $secure ? 443 : 80;
    }
    return [$secure, $port];
  }

  /**
   * {@inheritdoc}
   */
  public static function instanceFromUrl(CoreInterface $core, string $uri, array $values): Domain {
    $uri_parts = parse_url($uri);
    $storage = \Drupal::entityTypeManager()->getStorage('drd_domain');

    $query = \Drupal::entityQuery('drd_domain')
      ->accessCheck(FALSE)
      ->condition('domain', $uri_parts['host']);
    if (isset($uri_parts['path'])) {
      $query->condition('uripath', $uri_parts['path']);
    }
    else {
      $query->notExists('uripath');
    }
    if (isset($uri_parts['port'])) {
      $query->condition('port', $uri_parts['port']);
    }
    $ids = $query->execute();

    if (empty($ids)) {
      [$secure, $port] = self::calculateScheme($uri_parts);
      if (!$core->isNew()) {
        $values['core'] = $core->id();
      }
      $values['domain'] = $uri_parts['host'];
      $values['uripath'] = $uri_parts['path'] ?? '';
      $values['secure'] = $secure;
      $values['port'] = $port;
      /** @var Domain $domain */
      $domain = $storage->create($values);
    }
    else {
      /** @var Domain $domain */
      $domain = $storage->load(reset($ids));
    }
    return $domain;
  }

  /**
   * {@inheritdoc}
   */
  public function buildUrl($query = ''): Url {
    $uri = $this->get('secure')->value ? 'https' : 'http';
    $uri .= '://' . $this->get('domain')->value;
    if (!$this->isDefaultPort()) {
      $uri .= ':' . $this->get('port')->value;
    }
    $uripath = trim($this->get('uripath')->value ?: '', '/');
    if (!empty($uripath)) {
      $uri .= '/' . $uripath;
    }
    if ($this->cleanUrl && !empty($query)) {
      $uri .= '/' . $query;
    }
    $url = Url::fromUri($uri);
    if (!$this->cleanUrl && !empty($query)) {
      $url->setOption('query', ['q' => $query]);
    }
    return $url;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedCryptMethods(bool $cleanUrl = TRUE): mixed {
    $this->cleanUrl = $cleanUrl;

    /** @var \Drupal\drd\HttpRequest $request */
    $request = \Drupal::service('drd.http_request');
    $request->setDomain($this)
      ->setQuery('drd-agent-crypt')
      ->request();

    if (!$request->isRemoteDrd()) {
      if ($cleanUrl) {
        // Let's try again without clean URLs.
        return $this->getSupportedCryptMethods(FALSE);
      }
      if ($request->getStatusCode() === 200) {
        // We received proper response from a website, but not from DRD remote.
        return NULL;
      }
      // We haven't received anything from remote.
      return FALSE;
    }

    try {
      return json_decode($request->getResponse(), TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\Exception) {
      // We got some response which is not from drd_agent.
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function authorizeBySecret(string $method, array $secrets): bool {
    try {
      $body = base64_encode(json_encode([
        'remoteSetupToken' => $this->getRemoteSetupToken(FALSE),
        'method' => $method,
        'secrets' => $secrets,
      ], JSON_THROW_ON_ERROR));
    }
    catch (\JsonException) {
      return FALSE;
    }
    /** @var \Drupal\drd\HttpRequest $remote */
    $remote = \Drupal::service('drd.http_request');
    $remote
      ->setDomain($this)
      ->setQuery('drd-agent-authorize-secret')
      ->setOption('body', $body)
      ->request();
    return ($remote->getStatusCode() === 200);
  }

  /**
   * {@inheritdoc}
   */
  public function pushOtt(string $token): bool {
    $this->buildUrl('drd-agent');
    try {
      $body = base64_encode(json_encode([
        'uuid' => $this->uuid(),
        'args' => 'none',
        'iv' => 'none',
        'ott' => $token,
        'config' => $this->getRemoteSetupToken(FALSE),
      ], JSON_THROW_ON_ERROR));
    }
    catch (\JsonException) {
      return FALSE;
    }
    /** @var \Drupal\drd\HttpRequest $remote */
    $remote = \Drupal::service('drd.http_request');
    $remote
      ->setDomain($this)
      ->setQuery('drd-agent')
      ->setOption('body', $body)
      ->request();
    return ($remote->getStatusCode() === 200);
  }

  /**
   * {@inheritdoc}
   */
  public function isDefaultPort(): bool {
    $secure = (bool) $this->get('secure')->value;
    $port = (int) $this->get('port')->value;
    return $secure ? ($port === 443) : ($port === 80);
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteSetupToken(bool|string $redirect): string {
    global $base_url;
    $uri_parts = parse_url($base_url);
    $ipv4 = $ipv6 = [];
    \Drupal::service('drd.dnslookup')->lookup($uri_parts['host'], $ipv4, $ipv6);
    $values = [
      'uuid' => $this->get('uuid')->value,
      'auth' => $this->get('auth')->value,
      'authsetting' => $this->getAuthSetting(NULL, TRUE),
      'crypt' => $this->get('crypt')->value,
      'cryptsetting' => $this->getCryptSetting(),
      'redirect' => $redirect,
      'drdips' => [
        'v4' => $ipv4,
        'v6' => $ipv6,
      ],
    ];

    try {
      $values = base64_encode(json_encode($values, JSON_THROW_ON_ERROR));
    }
    catch (\JsonException) {
      return '';
    }
    return strtr($values, ['+' => '-', '/' => '_', '=' => '']);
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteLoginLink(string $label): GeneratedLink {
    $url = $this->buildUrl('user/login');
    $url->setOption('attributes', ['target' => '_blank']);
    return \Drupal::linkGenerator()->generate($label, $url);
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteSetupRedirect(bool $initial = FALSE): Url {
    if ($initial) {
      $redirect = new Url('entity.drd_domain.return_remote', ['domain' => $this->id()], ['absolute' => TRUE]);
      $redirect->setOption('query', ['token' => \Drupal::csrfToken()->get($redirect->getInternalPath())]);
    }
    else {
      $redirect = new Url('entity.drd_core.collection', [], ['absolute' => TRUE]);
    }
    return $redirect;
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteSetupLink(string $label, bool $initial = FALSE): GeneratedLink {
    $url = $this->buildUrl('drd-agent-authorize');
    return \Drupal::linkGenerator()->generate($label, $url);
  }

  /**
   * {@inheritdoc}
   */
  public function getLocalUrl(): string {
    return implode('.', [
      'id' . $this->id(),
      'drd',
      'localhost',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getSessionUrl(): string {
    return $this->actionManager->response('drd_action_session', $this);
  }

  /**
   * {@inheritdoc}
   */
  public function database(): array {
    if (empty($this->databases)) {
      $this->databases = $this->actionManager->response('drd_action_database', $this);
    }
    return $this->databases;
  }

  /**
   * {@inheritdoc}
   */
  public function download(string $source, string $destination): bool {
    return $this->actionManager->response('drd_action_download', $this, [
      'source' => $source,
      'destination' => $destination,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function ping(): bool {
    return $this->actionManager->response('drd_action_ping', $this);
  }

  /**
   * {@inheritdoc}
   */
  public function remoteInfo(): bool|array {
    return $this->actionManager->response('drd_action_info', $this);
  }

  /**
   * {@inheritdoc}
   */
  public function initCore(CoreInterface $core): bool {
    $response = $this->remoteInfo();

    if (!$response) {
      return FALSE;
    }

    // Set Drupal root directory.
    $core->set('drupalroot', $response['root']);

    // Set Drupal version.
    $release = Release::findOrCreate('core', 'drupal', $response['version']);
    $core->setDrupalRelease($release);

    // Save core.
    $core->save();

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function initValues(string $name, ?string $crypt = NULL, array $crypt_setting = []): void {
    $this->setName($name);
    // Initialize settings.
    if (empty($crypt)) {
      $crypt = 'OpenSsl';
      $crypt_setting = ['cipher' => 'aes-128-cbc'];
    }
    $crypt_setting['password'] = \Drupal::service('password_generator')->generate(50);
    $this->set('auth', 'shared_secret');
    $this->set('crypt', $crypt);
    $this
      ->setAuthSetting(['shared_secret' => ['secret' => \Drupal::service('password_generator')->generate(50)]])
      ->setCryptSetting([$crypt => $crypt_setting]);
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveAllDomains(CoreInterface $core): void {
    $this->set('core', $core->id());
    try {
      $this->save();
    }
    catch (EntityStorageException) {
      // Ignore.
    }
    $this->actionManager->response('drd_action_domains_receive', $core);
  }

  /**
   * {@inheritdoc}
   */
  public function setAliase(array $aliase): DomainInterface {
    $this->set('aliase', $aliase);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function updateScheme(string $url): DomainInterface {
    [$secure, $port] = self::calculateScheme(parse_url($url));
    $this->set('secure', $secure);
    $this->set('port', $port);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setReleases(array $releases): DomainInterface {
    $this->set('releases', $releases);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getReleases(): array {
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $releases */
    $releases = $this->get('releases');
    /** @var \Drupal\drd\Entity\ReleaseInterface[] $entities */
    $entities = $releases->referencedEntities();
    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function getMessages(): array {
    $cache = $this->cacheGet($this->cid('msg'));
    if ($cache) {
      return $cache->data;
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getReview(): array {
    $cache = $this->cacheGet($this->cid('review'));
    if ($cache) {
      return $cache->data;
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getMonitoring(): array {
    $cache = $this->cacheGet($this->cid('monitoring'));
    if ($cache) {
      return $cache->data;
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryResult(): array {
    $cache = $this->cacheGet($this->cid('queryresult'));
    if ($cache) {
      return $cache->data;
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getMaintenanceMode(bool $refresh = TRUE): bool {
    $cache = $this->cacheGet($this->cid('maintenancemode'));
    if ($cache) {
      return $cache->data;
    }

    if ($refresh) {
      // We do not know the status, let's get it from remote.
      $this->actionManager->response('drd_action_maintenance_mode', $this);
      return $this->getMaintenanceMode(FALSE);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getLatestPingStatus(bool $refresh = TRUE): ?bool {
    $cache = $this->cacheGet($this->cid('latest_ping_status'));
    if ($cache) {
      return $cache->data;
    }

    if ($refresh) {
      $this->ping();
      return $this->getLatestPingStatus(FALSE);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteBlock(string $module, string $delta, bool $refresh = TRUE): bool|string {
    $cache = $this->cacheGet($this->cid(implode(':', ['block', $module, $delta])));
    if ($cache) {
      return $cache->data;
    }

    if ($refresh) {
      $this->actionManager->response('drd_action_blocks', $this, [
        'module' => $module,
        'delta' => $delta,
      ]);
      return $this->getRemoteBlock($module, $delta, FALSE);
    }
    return FALSE;
  }

  /**
   * Get a specific part of the info array from the remote domain.
   *
   * @param string $part
   *   Part to be extracted.
   * @param bool $refresh
   *   Whether to refresh the info from the remote domain if we don't have
   *   fresh data in cache available.
   *
   * @return array
   *   The requisted info part.
   */
  private function getInfoPart(string $part, bool $refresh = TRUE): array {
    $cache = $this->cacheGet($this->cid($part));
    if ($cache) {
      return $cache->data;
    }

    if ($refresh) {
      $this->actionManager->response('drd_action_info', $this);
      return $this->getInfoPart($part, FALSE);
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteSettings(): array {
    return $this->getInfoPart('settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteGlobals(): array {
    return $this->getInfoPart('globals');
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteRequirements(): array {
    return $this->getInfoPart('requirements');
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
    return implode(':', ['drd_domain', $this->uuid(), $topic]);
  }

  /**
   * Get cache tags.
   *
   * @return array
   *   The cache tags.
   */
  private function ctags(): array {
    return [];
  }

  /**
   * Get cache lifetime.
   *
   * @return int
   *   The cache lifetime.
   */
  private function ctime(): int {
    return \Drupal::time()->getRequestTime() + 600;
  }

  /**
   * Sanitize all parts of a remote response.
   *
   * @param array|string|TranslatableMarkup $text
   *   The text or texts that need to be sanitized.
   * @param bool $translatable
   *   Whether the text is translatable.
   *
   * @return string|array|TranslatableMarkup
   *   The sanitized text or texts.
   */
  private function sanitizeRemoteText(array|string|TranslatableMarkup $text, bool $translatable = TRUE): string|array|TranslatableMarkup {
    if (is_array($text)) {
      $result = [];
      foreach ($text as $key => $line) {
        if (is_array($line) || is_string($line) || $line instanceof TranslatableMarkup) {
          $result[$key] = $this->sanitizeRemoteText($line, $translatable);
        }
        else {
          $result[$key] = $line;
        }
      }
    }
    elseif (is_string($text)) {
      $text = str_replace([
        'href="/',
        'destination=',
      ], [
        'target="_blank" href="' . $this->buildUrl()->toString() . '/',
        'destoff=',
      ], $text);
      if ($translatable) {
        $result = new FormattableMarkup($text, []);
      }
      else {
        $result = $text;
      }
    }
    elseif ($text instanceof TranslatableMarkup) {
      // We extract the text so that we can adjust the links.
      $result = $this->sanitizeRemoteText($text->render());
    }
    elseif (is_scalar($text)) {
      $result = (string) $text;
    }
    else {
      $result = 'unknown';
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function cacheRemoteMessages(array $messages): DomainInterface {
    $cid = $this->cid('msg');
    $cache = $this->cacheGet($cid);
    $data = $cache ? $cache->data : [];
    foreach ($messages as $type => $items) {
      foreach ($items as $item) {
        $data[$type] = [
          'ts' => \Drupal::time()->getRequestTime(),
          'msg' => $this->sanitizeRemoteText($item),
        ];
      }
    }
    $this->cacheSet($cid, $data, Cache::PERMANENT, $this->ctags());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function cacheMaintenanceMode(bool $mode): DomainInterface {
    $this->cacheSet($this->cid('maintenancemode'), $mode, $this->ctime(), $this->ctags());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function cacheBlock(string $module, string $delta, string $content): DomainInterface {
    $this->cacheSet($this->cid(implode(':', ['block', $module, $delta])), $this->sanitizeRemoteText($content, FALSE), $this->ctime(), $this->ctags());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function cachePingResult(?bool $status): DomainInterface {
    $cid = $this->cid('latest_ping_status');
    if (isset($status)) {
      $this->cacheSet($cid, $status, Cache::PERMANENT, $this->ctags());
    }
    elseif ($this->useCaches) {
      $this->cacheBackend->delete($cid);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function cacheErrorLog(string $log): DomainInterface {
    $this->cacheSet($this->cid('errorlog'), $log, Cache::PERMANENT, $this->ctags());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function cacheRequirements(array $requirements): DomainInterface {
    foreach ($requirements as $key => $requirement) {
      if (!empty($requirement['value'])) {
        $value = $this->sanitizeRemoteText($requirement['value'], FALSE);
        $requirements[$key]['value'] = is_string($value) ? new FormattableMarkup($value, []) : $value;
      }
      if (!empty($requirement['description'])) {
        $description = $this->sanitizeRemoteText($requirement['description'], FALSE);
        $requirements[$key]['description'] = is_string($description) ? new FormattableMarkup($description, []) : $description;
      }
    }
    $this->cacheSet($this->cid('requirements'), $requirements, $this->ctime(), $this->ctags());

    // Load the install file, that also loads install.inc from Drupal core.
    \Drupal::moduleHandler()->loadInclude('drd', 'install');
    $warnings = $errors = [];
    // Analyze the requirements and store warnings and errors with the domain.
    foreach ($requirements as $key => $value) {
      $label = $value['title'] ?? $key;
      try {
        $requirement = Requirement::findOrCreate($key, $label);
      }
      catch (\Exception) {
        // Ignore old drd_server requirements.
        continue;
      }
      if ($requirement->isIgnored()) {
        continue;
      }
      $severity = $value['severity'] ?? REQUIREMENT_INFO;
      switch ($severity) {
        case REQUIREMENT_ERROR:
          $errors[] = $requirement;
          break;

        case REQUIREMENT_WARNING:
          $warnings[] = $requirement;
          break;

      }
    }
    $this->set('warnings', $warnings);
    $this->set('errors', $errors);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function cacheVariables(array $variables): DomainInterface {
    $this->cacheSet($this->cid('variables'), $variables, Cache::PERMANENT, $this->ctags());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function cacheGlobals(array $globals): DomainInterface {
    $this->cacheSet($this->cid('globals'), $globals, Cache::PERMANENT, $this->ctags());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function cacheSettings(array $settings): DomainInterface {
    $this->cacheSet($this->cid('settings'), $settings, Cache::PERMANENT, $this->ctags());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function cacheReview(array $review): DomainInterface {
    $this->cacheSet($this->cid('review'), $review, Cache::PERMANENT, $this->ctags());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function cacheMonitoring(array $monitoring): DomainInterface {
    $this->cacheSet($this->cid('monitoring'), $monitoring, Cache::PERMANENT, $this->ctags());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function cacheQueryResult(string $query, string $info, array $headers, array $rows): DomainInterface {
    $this->cacheSet($this->cid('queryresult'), [
      'query' => $query,
      'info' => $info,
      'headers' => $headers,
      'rows' => $rows,
    ], Cache::PERMANENT, $this->ctags());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getHeader(): array {
    $core = $this->getCore();
    $headers = $core === NULL ? [] : $core->getHeader();
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
  public function resetCryptSettings(): DomainInterface {
    $crypt_methods_remote = $this->getSupportedCryptMethods();
    if ($crypt_methods_remote === FALSE) {
      $this->messenger()->addMessage($this->t('Can not connect to domain @url.', ['@url' => $this->get('domain')->value]), 'error');
      return $this;
    }
    if (empty($crypt_methods_remote)) {
      $this->messenger()->addMessage($this->t('There is no DRD Agent available at domain @url.', ['@url' => $this->get('domain')->value]), 'error');
      return $this;
    }

    if (CryptBase::countAvailableMethods($crypt_methods_remote) === 0) {
      $this->messenger()->addMessage($this->t('The remote site @url has DRD Agent installed but does not support any encryption methods matching those of the dashboard.', ['@url' => $this->get('domain')->value]), 'error');
      return $this;
    }
    $crypt_methods_local = CryptBase::getMethods();

    $method = 'OpenSsl';
    if (!isset($crypt_methods_local[$method], $crypt_methods_remote[$method])) {
      foreach ($crypt_methods_local as $value) {
        if (isset($crypt_methods_remote[$value])) {
          $method = $value;
          break;
        }
      }
    }

    $crypt = CryptBase::getInstance($method, []);
    if ($crypt->requiresPassword()) {
      $crypt->resetPassword();
    }
    $this->set('crypt', $method);
    $this->setCryptSetting([$method => $crypt->getSettings()], TRUE);

    return $this->reset();
  }

  /**
   * {@inheritdoc}
   */
  public function reset(): DomainInterface {
    $this->setName(NULL);
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
  public function renderPingStatus(): string {
    $status = $this->getLatestPingStatus(FALSE);
    if ($status === NULL) {
      return '';
    }
    if ($status) {
      $type = 'ok';
      $title = $this->t('Ping OK');
    }
    else {
      $type = 'failure';
      $title = $this->t('Does not respond');
    }
    return '<div class="drd-ping-info ' . $type . '"><span class="drd-icon">&nbsp;</span><div class="label">' . $title->render() . '</div></div>';
  }

}
