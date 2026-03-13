<?php

namespace Drupal\drd_agent\Agent\Action;

use Drupal\Component\Datetime\Time;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\State\StateInterface;
use Drupal\drd_agent\Agent\Auth\Base as AuthBase;
use Drupal\drd_agent\Agent\Auth\BaseInterface as AuthBaseInterface;
use Drupal\drd_agent\Crypt\Base as CryptBase;
use Drupal\drd_agent\Crypt\BaseMethodInterface;
use Drupal\drd_agent\DrdPiAuthManager;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Remote DRD Action Code.
 */
class Base implements BaseInterface, ContainerInjectionInterface {

  // The following 3 constants are required by DRD to build and verify the auth
  // method in the client handshake.
  // @see \Drupal\drd_pi\DrdPiAccountInterface::getAuthorizationMethod
  public const SEC_AUTH_ACQUIA = 'Acquia';
  public const SEC_AUTH_PANTHEON = 'Pantheon';
  public const SEC_AUTH_PLATFORMSH = 'PlatformSH';

  /**
   * The debug mode.
   *
   * @var bool
   */
  private bool $debugMode = FALSE;

  /**
   * The arguments.
   *
   * @var array
   */
  private array $arguments = [];

  /**
   * Crypt object for this DRD request.
   *
   * @var \Drupal\drd_agent\Crypt\BaseMethodInterface|bool
   */
  protected BaseMethodInterface|bool $crypt;

  /**
   * The account switcher.
   *
   * @var \Drupal\Core\Session\AccountSwitcherInterface
   */
  protected AccountSwitcherInterface $accountSwitcher;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The container interface.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected ContainerInterface $container;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected MessengerInterface $messenger;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * The time.
   *
   * @var \Drupal\Component\Datetime\Time
   */
  protected Time $time;

  /**
   * The DrdPi authentication manager.
   *
   * @var \Drupal\drd_agent\DrdPiAuthManager
   */
  protected DrdPiAuthManager $drdPiAuthManager;

  /**
   * Base constructor.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   * @param \Drupal\Core\Session\AccountSwitcherInterface $account_switcher
   *   The account switcher.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Database\Connection $database
   *   The databse connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The filesystem.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_channel_factory
   *   The logger channel.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state.
   * @param \Drupal\Component\Datetime\Time $time
   *   The time.
   * @param \Drupal\drd_agent\DrdPiAuthManager $drd_pi_auth_manager
   *   The DrdPi authentication manager.
   */
  final public function __construct(ContainerInterface $container, AccountSwitcherInterface $account_switcher, ConfigFactoryInterface $config_factory, Connection $database, EntityTypeManagerInterface $entity_type_manager, FileSystemInterface $file_system, LoggerChannelFactoryInterface $logger_channel_factory, MessengerInterface $messenger, ModuleHandlerInterface $module_handler, StateInterface $state, Time $time, DrdPiAuthManager $drd_pi_auth_manager) {
    $this->accountSwitcher = $account_switcher;
    $this->configFactory = $config_factory;
    $this->container = $container;
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
    $this->fileSystem = $file_system;
    $this->logger = $logger_channel_factory->get('DRD Agent');
    $this->messenger = $messenger;
    $this->moduleHandler = $module_handler;
    $this->state = $state;
    $this->time = $time;
    $this->drdPiAuthManager = $drd_pi_auth_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container,
      $container->get('account_switcher'),
      $container->get('config.factory'),
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('file_system'),
      $container->get('logger.factory'),
      $container->get('messenger'),
      $container->get('module_handler'),
      $container->get('state'),
      $container->get('datetime.time'),
      $container->get('plugin.manager.drd_pi_auth')
    );
  }

  /**
   * Recursively convert request arguments to an array.
   *
   * @param mixed $items
   *   Arguments to convert.
   *
   * @return mixed
   *   Array of all the given arguments.
   */
  private function toArray(mixed $items): mixed {
    foreach ($items as $key => $item) {
      if (is_object($item)) {
        $items[$key] = $this->toArray((array) $item);
      }
      elseif (is_array($item)) {
        $items[$key] = $this->toArray($item);
      }
    }
    return $items;
  }

  /**
   * Read and decode the input from the POST request.
   *
   * @param bool $debugMode
   *   Whether we operate in debug mode.
   * @param string|null $message
   *   Optional warning to output in watchdog.
   *
   * @return array
   *   The decoded input.
   *
   * @throws \Exception
   */
  private function readInput(bool $debugMode, ?string $message = NULL): array {
    $this->setDebugMode($debugMode);
    if (isset($message)) {
      $this->watchdog($message, [], 4);
    }

    $raw_input = file_get_contents('php://input');
    if (empty($raw_input)) {
      throw new \RuntimeException('Can not read input');
    }

    $input = json_decode(base64_decode($raw_input), TRUE, 512, JSON_THROW_ON_ERROR);
    if (!is_array($input) || empty($input)) {
      throw new \RuntimeException('Input is empty');
    }

    return $input;
  }

  /**
   * Main callback to execute an action.
   *
   * @param bool $debugMode
   *   Whether we operate in debug mode.
   *
   * @return string|mixed
   *   Encrypted and base64 encoded result from the executed action.
   */
  public function run(bool $debugMode = FALSE): mixed {
    try {
      $input = $this->readInput($debugMode);

      if (empty($input['uuid']) || empty($input['args']) || !isset($input['iv'])) {
        throw new \RuntimeException('Input is incomplete');
      }
      $input['args'] = base64_decode($input['args']);
      $input['iv'] = base64_decode($input['iv']);

      if (!empty($input['ott']) && !empty($input['config'])) {
        if (!$this->ott($input['ott'], $input['config'])) {
          throw new \RuntimeException('OTT config failed');
        }
        return 'ok';
      }

      if (!empty($input['auth']) && !empty($input['authsetting'])) {
        $this->authenticate($input['uuid'], $input);
      }

      $this->crypt = $this->getCryptInstance($input['uuid']);
      if (!$this->crypt) {
        throw new \RuntimeException('Encryption method not available or unauthorised');
      }
      $args = $this->toArray($this->crypt->decrypt($input['args'], $input['iv']));

      if (empty($args['auth']) || !isset($args['authsetting']) || empty($args['action'])) {
        throw new \RuntimeException('Arguments incomplete');
      }

      if (empty($input['auth'])) {
        // Let's authenticate here if we haven't yet authenticated
        // before decryption.
        $this->authenticate($input['uuid'], $args);
      }

      $action = $args['action'];
      $actionModule = $args['drd_action_module'];
      if ($actionModule === 'drd') {
        $actionModule = 'drd_agent';
      }
      if (isset($args['drd_action_plugin'])) {
        $actionFile = $this->realPath('temporary://drd_agent_' . $action . '.php');
        file_put_contents($actionFile, $args['drd_action_plugin']);
        unset($args['drd_action_plugin']);
        require_once $actionFile;
      }
      unset($args['auth'], $args['authsetting'], $args['action'], $args['drd_action_module']);
      $this->arguments = $args;
    }
    catch (\Exception $ex) {
      $this->watchdog($ex->getMessage(), [], 3);
      header('HTTP/1.1 502 Error');
      print 'error';
      exit;
    }

    try {
      $this->promoteUser();
      $classname = "\\Drupal\\$actionModule\\Agent\\Action\\$action";

      /** @var \Drupal\drd_agent\Agent\Action\BaseInterface $actionObject */
      $actionObject = call_user_func([$classname, 'create'], $this->container);
      $actionObject->init($this->crypt, $this->arguments, $this->debugMode);
    }
    catch (\Exception) {
      $this->watchdog('Not yet implemented: ' . $action, [], 3);
      header('HTTP/1.1 403 Not found');
      print 'Not yet implemented';
      exit;
    }

    $result = $actionObject->execute();
    if (is_array($result)) {
      $result['messages'] = $this->getMessages();
      return base64_encode($this->crypt->encrypt($result));
    }
    return $result;
  }

  /**
   * Authenticate the request or throw an exception.
   *
   * @param string $uuid
   *   The uuid of the calling DRD instance.
   * @param array $args
   *   Array of arguments.
   *
   * @throws \RuntimeException
   */
  private function authenticate(string $uuid, array $args): void {
    $auth_methods = AuthBase::getMethods($this->container);
    if (!isset($auth_methods[$args['auth']]) || !($auth_methods[$args['auth']] instanceof AuthBaseInterface)) {
      throw new \RuntimeException('Unrecognized authentication method');
    }

    $auth = $auth_methods[$args['auth']];
    if (!$auth->validateUuid($uuid)) {
      throw new \RuntimeException('DRD instance not registered');
    }
    if (!$auth->validate($args['authsetting'])) {
      throw new \RuntimeException('Not authenticated');
    }
  }

  /**
   * Callback to authorize a DRD instance with a given secret.
   *
   * @param bool $debugMode
   *   Whether we operate in debug mode.
   *
   * @return string
   *   Encrypted and base64 encoded result from the executed action.
   */
  public function authorizeBySecret(bool $debugMode = FALSE): string {
    try {
      $input = $this->readInput($debugMode, 'Authorize DRD by secret');

      if (empty($input['remoteSetupToken']) || empty($input['method']) || empty($input['secrets'])) {
        throw new \RuntimeException('Input is incomplete');
      }

      /** @var \Drupal\drd_agent\Plugin\DrdPiAuth\DrdPiAuthInterface $auth */
      $auth = $this->drdPiAuthManager->createInstance($input['method']);
      $auth->validate($input);

      $this->authorize($input['remoteSetupToken']);
    }
    catch (\Exception $ex) {
      $this->watchdog($ex->getMessage(), [], 3);
      // Let's slow down to prevent brute force.
      sleep(10);
      header('HTTP/1.1 502 Error');
      print 'error';
      exit;
    }

    return 'ok';
  }

  /**
   * {@inheritdoc}
   */
  public function getArguments(): array {
    return $this->arguments;
  }

  /**
   * {@inheritdoc}
   */
  public function getDebugMode(): bool {
    return $this->debugMode;
  }

  /**
   * {@inheritdoc}
   */
  public function setDebugMode(bool $debugMode): BaseInterface {
    $this->debugMode = $debugMode;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function promoteUser(): BaseInterface {
    global $user;
    $user = User::load(1);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCryptInstance(string $uuid): BaseMethodInterface|bool {
    $authorised = $this->state->get('drd_agent.authorised', []);
    if (empty($authorised[$uuid])) {
      return FALSE;
    }

    return CryptBase::getInstance(
      $this->container,
      $authorised[$uuid]['crypt'],
      (array) $authorised[$uuid]['cryptsetting']
    );
  }

  /**
   * {@inheritdoc}
   */
  public function authorize(string $remoteSetupToken): BaseInterface {
    /** @var \Drupal\drd_agent\Setup $service */
    $service = $this->container->get('drd_agent.setup');
    $service
      ->setRemoteSetupToken($remoteSetupToken)
      ->execute();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDbInfo(): array {
    return $this->database->getConnectionOptions();
  }

  /**
   * Logging if in debug mode.
   *
   * {@inheritdoc}
   */
  public function watchdog(string $message, array $variables = [], int $severity = 5, ?string $link = NULL): BaseInterface {
    if ($this->getDebugMode()) {
      if ($link) {
        $variables['link'] = $link;
      }
      $this->logger->log($severity, $message, $variables);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function ott(string $ott, string $remoteSetupToken): bool {
    $storedOtt = $this->state->get('drd_agent.ott', FALSE);
    if (!$storedOtt) {
      $this->watchdog('No OTT available', [], RfcLogLevel::ERROR);
      return FALSE;
    }
    $this->state->delete('drd_agent.ott');
    if (empty($storedOtt['expires']) || $storedOtt['expires'] < $this->time->getRequestTime()) {
      $this->watchdog('OTT expired', [], RfcLogLevel::ERROR);
      return FALSE;
    }
    if (empty($storedOtt['token']) || $storedOtt['token'] !== $ott) {
      $this->watchdog('Token missmatch: :local / :remote', [':local' => $storedOtt['token'], ':remote' => $ott], RfcLogLevel::ERROR);
      return FALSE;
    }

    /** @var \Drupal\drd_agent\Setup $service */
    $service = $this->container->get('drd_agent.setup');
    $service
      ->setRemoteSetupToken($remoteSetupToken)
      ->execute();
    $this->watchdog('OTT config completed', [], RfcLogLevel::INFO);
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function realPath($path): string {
    return $this->fileSystem->realpath($path);
  }

  /**
   * {@inheritdoc}
   */
  public function getMessages(): array {
    return $this->messenger->all();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): mixed {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function init(BaseMethodInterface $crypt, array $arguments, bool $debugMode): void {
    $this->crypt = $crypt;
    $this->arguments = $arguments;
    $this->debugMode = $debugMode;
  }

}
