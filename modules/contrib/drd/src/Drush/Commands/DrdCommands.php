<?php

namespace Drupal\drd\Drush\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\drd\ActionManagerInterface;
use Drupal\drd\Cleanup;
use Drupal\drd\Logging;
use Drupal\drd\Plugin\Action\BaseEntityInterface;
use Drupal\drd\Plugin\Action\BaseGlobalInterface;
use Drupal\drd\Plugin\Action\BaseInterface;
use Drupal\drd\QueueManager;
use Drupal\drd\SelectEntitiesInterface;
use Drush\Attributes\Argument;
use Drush\Attributes\Command;
use Drush\Attributes\DefaultFields;
use Drush\Attributes\FieldLabels;
use Drush\Attributes\Hook;
use Drush\Attributes\Option;
use Drush\Attributes\Usage;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;

/**
 * Drush commands for DRD.
 *
 * @package Drupal\drd
 */
class DrdCommands extends DrushCommands {

  use DrdCommandsTrait;

  /**
   * The service for DRD entity selection.
   *
   * @var \Drupal\drd\SelectEntitiesInterface
   */
  protected SelectEntitiesInterface $entitiesService;

  /**
   * The cleanup service.
   *
   * @var \Drupal\drd\Cleanup
   */
  protected Cleanup $cleanup;

  /**
   * Constructor for Drush commands.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\drd\Logging $logging
   *   The logging channel.
   * @param \Drupal\drd\SelectEntitiesInterface $entities_service
   *   The service for DRD entity selection.
   * @param \Drupal\drd\QueueManager $queue_manager
   *   The queue manager.
   * @param \Drupal\drd\Cleanup $cleanup
   *   The cleanup service.
   * @param \Drupal\drd\ActionManagerInterface $actionManager
   *   The DRD action manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountProxyInterface $current_user, Logging $logging, SelectEntitiesInterface $entities_service, QueueManager $queue_manager, Cleanup $cleanup, ActionManagerInterface $actionManager) {
    parent::__construct();
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->logging = $logging;
    $this->entitiesService = $entities_service;
    $this->queueManager = $queue_manager;
    $this->cleanup = $cleanup;
    if (Drush::verbose()) {
      $this->logging->enforceDebug();
    }
    $this->actionManager = $actionManager;
  }

  /**
   * Return an instance of these Drush commands.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   *
   * @return \Drupal\drd\Drush\Commands\DrdCommands
   *   The instance of Drush commands.
   */
  public static function create(ContainerInterface $container): DrdCommands {
    return new DrdCommands(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('drd.logging'),
      $container->get('drd.entities.select'),
      $container->get('queue.drd'),
      $container->get('drd.cleanup'),
      $container->get('plugin.manager.drd_action'),
    );
  }

  /**
   * Callback to validate arguments from the command line.
   *
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   *   Source of the command data.
   * @param array $arguments
   *   List of argument ids that are expected from the command line.
   *
   * @throws \Exception
   */
  protected function validateArguments(CommandData $commandData, array $arguments): void {
    $commandArguments = $commandData->arguments();
    foreach ($arguments as $argument) {
      if (empty($commandArguments[$argument])) {
        throw new MissingMandatoryParametersException('Missing argument ' . $argument);
      }
    }
  }

  /**
   * Load and configure service to select entities.
   *
   * @return \Drupal\drd\SelectEntitiesInterface
   *   DRD service for entity selction.
   */
  protected function service(): SelectEntitiesInterface {
    return $this->entitiesService
      ->setTag($this->options['tag'])
      ->setHost($this->options['host'])
      ->setHostId($this->options['host-id'])
      ->setCore($this->options['core'])
      ->setCoreId($this->options['core-id'])
      ->setDomain($this->options['domain'])
      ->setDomainId($this->options['domain-id']);
  }

  /**
   * Prepare services, the action and their arguments.
   *
   * @param array $arguments
   *   Associated array with arguments.
   * @param array $options
   *   List of option keys.
   *
   * @return $this
   */
  protected function prepare(array $arguments = [], array $options = []): self {
    $this->logging->setIO($this->io());
    $this->action = $this->actionManager->instance($this->actionKey);
    if (!($this->action instanceof BaseInterface)) {
      return $this;
    }
    try {
      /** @var \Drupal\Core\Session\AccountInterface $account */
      $account = $this->entityTypeManager->getStorage('user')->load(1);
      $this->currentUser->setAccount($account);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->logging->log('error', $e->getMessage());
    }

    foreach ($arguments as $key => $value) {
      $this->action->setActionArgument($key, $value);
    }

    foreach ($options as $key) {
      if (isset($this->options[$key])) {
        $this->action->setActionArgument($key, $this->options[$key]);
      }
    }

    return $this;
  }

  /**
   * Callback to execute the prepared action.
   *
   * @return false|mixed
   *   The execution result of FALSE otherwise.
   */
  protected function execute(): mixed {
    if (empty($this->action)) {
      return FALSE;
    }
    if ($this->action instanceof BaseGlobalInterface) {
      $this->entities[] = FALSE;
    }
    elseif (empty($this->entities)) {
      return FALSE;
    }

    $result = FALSE;
    $ok = $failure = 0;
    $this->io()->title('Executing ' . $this->action->getPluginDefinition()['label']);
    foreach ($this->entities as $entity) {
      if ($entity) {
        /** @var \Drupal\drd\Entity\BaseInterface $entity */
        /** @var \Drupal\drd\Plugin\Action\BaseEntityInterface $action */
        $action = $this->action;
        $this->io()->section('- on id ' . $entity->id() . ': ' . $entity->getName());
        $result = $this->actionManager->executeAction($action, $entity);
      }
      elseif ($this->action instanceof BaseEntityInterface || $this->action instanceof BaseGlobalInterface) {
        $result = $this->actionManager->executeAction($this->action);
      }
      if ($result !== FALSE) {
        $this->io()->success('  ok!');
        $ok++;
      }
      else {
        $this->io()->error('  failure!');
        $failure++;
      }

      $output = $this->action->getOutput();
      if ($output) {
        foreach ($output as $value) {
          $this->io()->write('  ' . $value, TRUE);
        }
      }
    }

    $this->queueManager->processAll();

    if (empty($failure)) {
      $this->io()->success('Completed!');
    }
    elseif (empty($ok)) {
      $this->io()->error('Completed!');
    }
    else {
      $this->io()->warning('Completed!');
    }
    return $result;
  }

  /**
   * Retrieve blocks from remote domain(s).
   *
   * @param array $options
   *   CLI options.
   */
  #[Command(name: 'drd:blocks', aliases: ['drd-blocks'])]
  #[Option(name: 'tag', description: 'The tag name.')]
  #[Option(name: 'host', description: 'The host name.')]
  #[Option(name: 'host-id', description: 'The host ID.')]
  #[Option(name: 'core', description: 'The core name.')]
  #[Option(name: 'core-id', description: 'The core ID.')]
  #[Option(name: 'domain', description: 'The domain name.')]
  #[Option(name: 'domain-id', description: 'The domain ID.')]
  #[Option(name: 'module', description: 'The remote module from which to receive blocks.')]
  #[Option(name: 'delta', description: 'The identifier of a specific block within a module.')]
  #[Usage(name: 'drush drd:blocks', description: 'Retrieve blocks from remote domain(s).')]
  public function blocks(
    array $options = [
      'tag' => NULL,
      'host' => NULL,
      'host-id' => NULL,
      'core' => NULL,
      'core-id' => NULL,
      'domain' => NULL,
      'domain-id' => NULL,
      'module' => NULL,
      'delta' => NULL,
    ],
  ): void {
    $this->actionKey = 'drd_action_blocks';
    $this->options = $options;
    $this->entities = $this->service()->domains();
    $this
      ->prepare([], ['module', 'delta'])
      ->execute();
  }

  /**
   * Run cron on remote domain(s).
   *
   * @param array $options
   *   CLI options.
   */
  #[Command(name: 'drd:cron', aliases: ['drd-cron'])]
  #[Option(name: 'tag', description: 'The tag name.')]
  #[Option(name: 'host', description: 'The host name.')]
  #[Option(name: 'host-id', description: 'The host ID.')]
  #[Option(name: 'core', description: 'The core name.')]
  #[Option(name: 'core-id', description: 'The core ID.')]
  #[Option(name: 'domain', description: 'The domain name.')]
  #[Option(name: 'domain-id', description: 'The domain ID.')]
  #[Usage(name: 'drush drd:cron', description: 'Run cron on remote domain(s).')]
  public function cron(
    array $options = [
      'tag' => NULL,
      'host' => NULL,
      'host-id' => NULL,
      'core' => NULL,
      'core-id' => NULL,
      'domain' => NULL,
      'domain-id' => NULL,
    ],
  ): void {
    $this->actionKey = 'drd_action_cron';
    $this->options = $options;
    $this->entities = $this->service()->domains();
    $this
      ->prepare()
      ->execute();
  }

  /**
   * Download database from remote domain(s).
   *
   * @param array $options
   *   CLI options.
   */
  #[Command(name: 'drd:database', aliases: ['drd-database'])]
  #[Option(name: 'tag', description: 'The tag name.')]
  #[Option(name: 'host', description: 'The host name.')]
  #[Option(name: 'host-id', description: 'The host ID.')]
  #[Option(name: 'core', description: 'The core name.')]
  #[Option(name: 'core-id', description: 'The core ID.')]
  #[Option(name: 'domain', description: 'The domain name.')]
  #[Option(name: 'domain-id', description: 'The domain ID.')]
  #[Usage(name: 'drush drd:database', description: 'Download database from remote domain(s).')]
  public function database(
    array $options = [
      'tag' => NULL,
      'host' => NULL,
      'host-id' => NULL,
      'core' => NULL,
      'core-id' => NULL,
      'domain' => NULL,
      'domain-id' => NULL,
    ],
  ): void {
    $this->actionKey = 'drd_action_database';
    $this->options = $options;
    $this->entities = $this->service()->domains();
    $this
      ->prepare()
      ->execute();
  }

  /**
   * Command to determine all IP addresses of remote host(s).
   *
   * @param array $options
   *   CLI options.
   */
  #[Command(name: 'drd:dnslookup', aliases: ['drd-dnslookup'])]
  #[Option(name: 'tag', description: 'The tag name.')]
  #[Option(name: 'host', description: 'The host name.')]
  #[Option(name: 'host-id', description: 'The host ID.')]
  #[Option(name: 'core', description: 'The core name.')]
  #[Option(name: 'core-id', description: 'The core ID.')]
  #[Option(name: 'domain', description: 'The domain name.')]
  #[Option(name: 'domain-id', description: 'The domain ID.')]
  #[Usage(name: 'drush drd:dnslookup', description: 'Command to determine all IP addresses of remote host(s).')]
  public function dnslookup(
    array $options = [
      'tag' => NULL,
      'host' => NULL,
      'host-id' => NULL,
      'core' => NULL,
      'core-id' => NULL,
      'domain' => NULL,
      'domain-id' => NULL,
    ],
  ): void {
    $this->actionKey = 'drd_action_dnslookup';
    $this->options = $options;
    $this->entities = $this->service()->hosts();
    $this
      ->prepare()
      ->execute();
  }

  /**
   * Change the settings of a local domain entity.
   *
   * @param array $options
   *   CLI options.
   */
  #[Command(name: 'drd:domainchange', aliases: ['drd-domainchange'])]
  #[Option(name: 'tag', description: 'The tag name.')]
  #[Option(name: 'host', description: 'The host name.')]
  #[Option(name: 'host-id', description: 'The host ID.')]
  #[Option(name: 'core', description: 'The core name.')]
  #[Option(name: 'core-id', description: 'The core ID.')]
  #[Option(name: 'domain', description: 'The domain name.')]
  #[Option(name: 'domain-id', description: 'The domain ID.')]
  #[Option(name: 'newdomain', description: 'The new domain.')]
  #[Option(name: 'secure', description: 'The secure.')]
  #[Option(name: 'port', description: 'The port.')]
  #[Option(name: 'force', description: 'Force changing the domain.')]
  #[Usage(name: 'drush drd:domainchange', description: 'Change the settings of a local domain entity.')]
  public function domainchange(
    array $options = [
      'tag' => NULL,
      'host' => NULL,
      'host-id' => NULL,
      'core' => NULL,
      'core-id' => NULL,
      'domain' => NULL,
      'domain-id' => NULL,
      'newdomain' => NULL,
      'secure' => NULL,
      'port' => NULL,
      'force' => FALSE,
    ],
  ): void {
    $this->actionKey = 'drd_action_domainchange';
    $this->options = $options;
    $this->entities = $this->service()->domains();
    $this
      ->prepare([], ['newdomain', 'secure', 'port', 'force'])
      ->execute();
  }

  /**
   * Move a local domain record to a different core.
   *
   * @param int $dest_core_id
   *   Destination Core ID.
   * @param array $options
   *   CLI options.
   */
  #[Command(name: 'drd:domainmove', aliases: ['drd-domainmove'])]
  #[Option(name: 'tag', description: 'The tag name.')]
  #[Option(name: 'host', description: 'The host name.')]
  #[Option(name: 'host-id', description: 'The host ID.')]
  #[Option(name: 'core', description: 'The core name.')]
  #[Option(name: 'core-id', description: 'The core ID.')]
  #[Option(name: 'domain', description: 'The domain name.')]
  #[Option(name: 'domain-id', description: 'The domain ID.')]
  #[Usage(name: 'drush drd:domainmove', description: 'Move a local domain record to a different core.')]
  public function domainmove(
    int $dest_core_id,
    array $options = [
      'tag' => NULL,
      'host' => NULL,
      'host-id' => NULL,
      'core' => NULL,
      'core-id' => NULL,
      'domain' => NULL,
      'domain-id' => NULL,
    ],
  ): void {
    $this->actionKey = 'drd_action_domainchange';
    $this->options = $options;
    $this->entities = $this->service()->domains();
    $this
      ->prepare(['dest-core-id' => $dest_core_id], [
        'domain',
        'secure',
        'port',
        'force',
      ])
      ->execute();
  }

  /**
   * Validation callback for the DomainMove command.
   *
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   *   The command data to validate.
   *
   * @throws \Exception
   */
  #[Hook(type: HookManager::ARGUMENT_VALIDATOR, target: 'drd:domainmove')]
  public function validateDomainMove(CommandData $commandData): void {
    $this->validateArguments($commandData, ['dest_core_id']);
  }

  /**
   * Enable all domains of remote core(s).
   *
   * @param array $options
   *   CLI options.
   */
  #[Command(name: 'drd:domains:enableall', aliases: ['drd-domains-enableall'])]
  #[Option(name: 'tag', description: 'The tag name.')]
  #[Option(name: 'host', description: 'The host name.')]
  #[Option(name: 'host-id', description: 'The host ID.')]
  #[Option(name: 'core', description: 'The core name.')]
  #[Option(name: 'core-id', description: 'The core ID.')]
  #[Option(name: 'domain', description: 'The domain name.')]
  #[Option(name: 'domain-id', description: 'The domain ID.')]
  #[Usage(name: 'drush drd:domains:enableall', description: 'Enable all domains of remote core(s).')]
  public function domainsEnableall(
    array $options = [
      'tag' => NULL,
      'host' => NULL,
      'host-id' => NULL,
      'core' => NULL,
      'core-id' => NULL,
      'domain' => NULL,
      'domain-id' => NULL,
    ],
  ): void {
    $this->actionKey = 'drd_action_domains_enableall';
    $this->options = $options;
    $this->entities = $this->service()->cores();
    $this
      ->prepare()
      ->execute();
  }

  /**
   * Receive all domains from remote core(s).
   *
   * @param array $options
   *   CLI options.
   */
  #[Command(name: 'drd:domains:receive', aliases: ['drd-domains-receive'])]
  #[Option(name: 'tag', description: 'The tag name.')]
  #[Option(name: 'host', description: 'The host name.')]
  #[Option(name: 'host-id', description: 'The host ID.')]
  #[Option(name: 'core', description: 'The core name.')]
  #[Option(name: 'core-id', description: 'The core ID.')]
  #[Option(name: 'domain', description: 'The domain name.')]
  #[Option(name: 'domain-id', description: 'The domain ID.')]
  #[Usage(name: 'drush drd:domains:receive', description: 'Receive all domains of remote core(s).')]
  public function domainsReceive(
    array $options = [
      'tag' => NULL,
      'host' => NULL,
      'host-id' => NULL,
      'core' => NULL,
      'core-id' => NULL,
      'domain' => NULL,
      'domain-id' => NULL,
    ],
  ): void {
    $this->actionKey = 'drd_action_domains_receive';
    $this->options = $options;
    $this->entities = $this->service()->cores();
    $this
      ->prepare()
      ->execute();
  }

  /**
   * Download a file from remote domain(s).
   *
   * @param string $source
   *   Full remote path and filename which should be downloaded file.
   * @param string $destination
   *   Full local path and filename where to store the download file.
   * @param array $options
   *   CLI options.
   */
  #[Command(name: 'drd:download', aliases: ['drd-download'])]
  #[Argument(name: 'source', description: 'Full remote path and filename which should be downloaded file.')]
  #[Argument(name: 'destination', description: 'Full local path and filename where to store the download file.')]
  #[Option(name: 'tag', description: 'The tag name.')]
  #[Option(name: 'host', description: 'The host name.')]
  #[Option(name: 'host-id', description: 'The host ID.')]
  #[Option(name: 'core', description: 'The core name.')]
  #[Option(name: 'core-id', description: 'The core ID.')]
  #[Option(name: 'domain', description: 'The domain name.')]
  #[Option(name: 'domain-id', description: 'The domain ID.')]
  #[Usage(name: 'drush drd:download', description: 'Download a file from remote domain(s).')]
  public function download(
    string $source,
    string $destination,
    array $options = [
      'tag' => NULL,
      'host' => NULL,
      'host-id' => NULL,
      'core' => NULL,
      'core-id' => NULL,
      'domain' => NULL,
      'domain-id' => NULL,
    ],
  ): void {
    $this->actionKey = 'drd_action_download';
    $this->options = $options;
    $this->entities = $this->service()->domains();
    $this
      ->prepare(['source' => $source, 'destination' => $destination])
      ->execute();
  }

  /**
   * Validation callback for the Download command.
   *
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   *   The command data to validate.
   *
   * @throws \Exception
   */
  #[Hook(type: HookManager::ARGUMENT_VALIDATOR, target: 'drd:download')]
  public function validateDownload(CommandData $commandData): void {
    $this->validateArguments($commandData, ['source', 'destination']);
  }

  /**
   * Download error logs from remote domain(s).
   *
   * @param array $options
   *   CLI options.
   */
  #[Command(name: 'drd:errorlogs', aliases: ['drd-errorlogs'])]
  #[Option(name: 'tag', description: 'The tag name.')]
  #[Option(name: 'host', description: 'The host name.')]
  #[Option(name: 'host-id', description: 'The host ID.')]
  #[Option(name: 'core', description: 'The core name.')]
  #[Option(name: 'core-id', description: 'The core ID.')]
  #[Option(name: 'domain', description: 'The domain name.')]
  #[Option(name: 'domain-id', description: 'The domain ID.')]
  #[Usage(name: 'drush drd:errorlogs', description: 'Download error logs from remote domain(s).')]
  public function errorlogs(
    array $options = [
      'tag' => NULL,
      'host' => NULL,
      'host-id' => NULL,
      'core' => NULL,
      'core-id' => NULL,
      'domain' => NULL,
      'domain-id' => NULL,
    ],
  ): void {
    $this->actionKey = 'drd_action_error_logs';
    $this->options = $options;
    $this->entities = $this->service()->domains();
    $this
      ->prepare()
      ->execute();
  }

  /**
   * Flush all caches on remote domain(s).
   *
   * @param array $options
   *   CLI options.
   */
  #[Command(name: 'drd:flushcache', aliases: ['drd-flushcache'])]
  #[Option(name: 'tag', description: 'The tag name.')]
  #[Option(name: 'host', description: 'The host name.')]
  #[Option(name: 'host-id', description: 'The host ID.')]
  #[Option(name: 'core', description: 'The core name.')]
  #[Option(name: 'core-id', description: 'The core ID.')]
  #[Option(name: 'domain', description: 'The domain name.')]
  #[Option(name: 'domain-id', description: 'The domain ID.')]
  #[Usage(name: 'drush drd:flushcache', description: 'Flush all caches on remote domain(s).')]
  public function flushcache(
    array $options = [
      'tag' => NULL,
      'host' => NULL,
      'host-id' => NULL,
      'core' => NULL,
      'core-id' => NULL,
      'domain' => NULL,
      'domain-id' => NULL,
    ],
  ): void {
    $this->actionKey = 'drd_action_flush_cache';
    $this->options = $options;
    $this->entities = $this->service()->domains();
    $this
      ->prepare()
      ->execute();
  }

  /**
   * Receive information from remote domain(s).
   *
   * @param array $options
   *   CLI options.
   */
  #[Command(name: 'drd:info', aliases: ['drd-info'])]
  #[Option(name: 'tag', description: 'The tag name.')]
  #[Option(name: 'host', description: 'The host name.')]
  #[Option(name: 'host-id', description: 'The host ID.')]
  #[Option(name: 'core', description: 'The core name.')]
  #[Option(name: 'core-id', description: 'The core ID.')]
  #[Option(name: 'domain', description: 'The domain name.')]
  #[Option(name: 'domain-id', description: 'The domain ID.')]
  #[Usage(name: 'drush drd:info', description: 'Receive information from remote domain(s).')]
  public function info(
    array $options = [
      'tag' => NULL,
      'host' => NULL,
      'host-id' => NULL,
      'core' => NULL,
      'core-id' => NULL,
      'domain' => NULL,
      'domain-id' => NULL,
    ],
  ): void {
    $this->actionKey = 'drd_action_info';
    $this->options = $options;
    $this->entities = $this->service()->domains();
    $this
      ->prepare()
      ->execute();
  }

  /**
   * Run the job scheduler on remote domain(s).
   *
   * @param array $options
   *   CLI options.
   */
  #[Command(name: 'drd:jobscheduler', aliases: ['drd-jobscheduler'])]
  #[Option(name: 'tag', description: 'The tag name.')]
  #[Option(name: 'host', description: 'The host name.')]
  #[Option(name: 'host-id', description: 'The host ID.')]
  #[Option(name: 'core', description: 'The core name.')]
  #[Option(name: 'core-id', description: 'The core ID.')]
  #[Option(name: 'domain', description: 'The domain name.')]
  #[Option(name: 'domain-id', description: 'The domain ID.')]
  #[Usage(name: 'drush drd:jobscheduler', description: 'Run the job scheduler on remote domain(s).')]
  public function jobscheduler(
    array $options = [
      'tag' => NULL,
      'host' => NULL,
      'host-id' => NULL,
      'core' => NULL,
      'core-id' => NULL,
      'domain' => NULL,
      'domain-id' => NULL,
    ],
  ): void {
    $this->actionKey = 'drd_action_job_scheduler';
    $this->options = $options;
    $this->entities = $this->service()->domains();
    $this
      ->prepare()
      ->execute();
  }

  /**
   * Download and update translations on remote domain(s).
   *
   * @param array $options
   *   CLI options.
   */
  #[Command(name: 'drd:l10n:update', aliases: ['drd-l10n-update'])]
  #[Option(name: 'tag', description: 'The tag name.')]
  #[Option(name: 'host', description: 'The host name.')]
  #[Option(name: 'host-id', description: 'The host ID.')]
  #[Option(name: 'core', description: 'The core name.')]
  #[Option(name: 'core-id', description: 'The core ID.')]
  #[Option(name: 'domain', description: 'The domain name.')]
  #[Option(name: 'domain-id', description: 'The domain ID.')]
  #[Usage(name: 'drush drd:l10n:update', description: 'Download and update translations on remote domain(s).')]
  public function l10nUpdate(
    array $options = [
      'tag' => NULL,
      'host' => NULL,
      'host-id' => NULL,
      'core' => NULL,
      'core-id' => NULL,
      'domain' => NULL,
      'domain-id' => NULL,
    ],
  ): void {
    $this->actionKey = 'drd_action_update_translation';
    $this->options = $options;
    $this->entities = $this->service()->domains();
    $this
      ->prepare()
      ->execute();
  }

  /**
   * Output a list of cores with certain details.
   *
   * @param string $tag
   *   The tag for which to list actions.
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   The formatted rows with fields.
   */
  #[Command(name: 'drd:list:actions', aliases: ['drd-list-actions'])]
  #[Argument(name: 'tag', description: 'The tag for which to list actions.')]
  #[FieldLabels(
    labels:[
      'id' => 'The ID',
      'type' => 'The type',
      'label' => 'The label',
    ],
  )]
  #[DefaultFields(fields:['id', 'type', 'label'])]
  #[Usage(name: 'drush drd:list:actions', description: 'Output a list of cores with certain details.')]
  public function listActions(string $tag): RowsOfFields {
    $this->actionKey = 'drd_action_list_action';
    $this->setOutput(new NullOutput());
    $rows = [];
    foreach ($this->actionManager->getActionsByTerm($tag) as $action) {
      $rows[] = [
        'id' => $action->getPluginId(),
        'type' => $action->getPluginDefinition()['type'],
        'label' => $action->getPluginDefinition()['label'],
      ];
    }
    return new RowsOfFields($rows);
  }

  /**
   * Output a list of cores with certain details.
   *
   * @param array $options
   *   CLI options.
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   The formatted rows with fields.
   */
  #[Command(name: 'drd:list:cores', aliases: ['drd-list-cores'])]
  #[Option(name: 'tag', description: 'The tag name.')]
  #[Option(name: 'host', description: 'The host name.')]
  #[Option(name: 'host-id', description: 'The host ID.')]
  #[Option(name: 'core', description: 'The core name.')]
  #[Option(name: 'core-id', description: 'The core ID.')]
  #[Option(name: 'domain', description: 'The domain name.')]
  #[Option(name: 'domain-id', description: 'The domain ID.')]
  #[FieldLabels(
    labels:[
      'core-id' => 'The CID',
      'core-label' => 'The core label',
      'host-id' => 'The HID',
      'host-label' => 'The host label',
    ],
  )]
  #[DefaultFields(fields:['core-id', 'core-label', 'host-label'])]
  #[Usage(name: 'drush drd:list:cores', description: 'Output a list of cores with certain details.')]
  public function listCores(
    array $options = [
      'tag' => NULL,
      'host' => NULL,
      'host-id' => NULL,
      'core' => NULL,
      'core-id' => NULL,
      'domain' => NULL,
      'domain-id' => NULL,
      'format' => 'table',
    ],
  ): RowsOfFields {
    $this->actionKey = 'drd_action_list_cores';
    $this->options = $options;
    $this->setOutput(new NullOutput());
    $rows = $this
      ->prepare([], array_keys($options))
      ->execute();
    return new RowsOfFields($rows);
  }

  /**
   * Output a list of domains with certain details.
   *
   * @param array $options
   *   CLI options.
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   The formatted rows with fields.
   */
  #[Command(name: 'drd:list:domains', aliases: ['drd-list-domains'])]
  #[Option(name: 'tag', description: 'The tag name.')]
  #[Option(name: 'host', description: 'The host name.')]
  #[Option(name: 'host-id', description: 'The host ID.')]
  #[Option(name: 'core', description: 'The core name.')]
  #[Option(name: 'core-id', description: 'The core ID.')]
  #[Option(name: 'domain', description: 'The domain name.')]
  #[Option(name: 'domain-id', description: 'The domain ID.')]
  #[FieldLabels(
    labels:[
      'domain-id' => 'The DID',
      'domain-label' => 'The domain label',
      'domain' => 'The domain',
      'core-id' => 'The CID',
      'core-label' => 'The core label',
      'host-id' => 'The HID',
      'host-label' => 'The host label',
    ],
  )]
  #[DefaultFields(fields:['domain-id', 'domain-label', 'domain', 'core-label', 'host-label'])]
  #[Usage(name: 'drush drd:list:domains', description: 'Output a list of domains with certain details.')]
  public function listDomains(
    array $options = [
      'tag' => NULL,
      'host' => NULL,
      'host-id' => NULL,
      'core' => NULL,
      'core-id' => NULL,
      'domain' => NULL,
      'domain-id' => NULL,
      'format' => 'table',
    ],
  ): RowsOfFields {
    $this->actionKey = 'drd_action_list_domains';
    $this->options = $options;
    $this->setOutput(new NullOutput());
    $rows = $this
      ->prepare([], array_keys($options))
      ->execute();
    return new RowsOfFields($rows);
  }

  /**
   * Output a list of hosts with certain details.
   *
   * @param array $options
   *   CLI options.
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   The formatted rows with fields.
   */
  #[Command(name: 'drd:list:hosts', aliases: ['drd-list-hosts'])]
  #[Option(name: 'tag', description: 'The tag name.')]
  #[Option(name: 'host', description: 'The host name.')]
  #[Option(name: 'host-id', description: 'The host ID.')]
  #[Option(name: 'core', description: 'The core name.')]
  #[Option(name: 'core-id', description: 'The core ID.')]
  #[Option(name: 'domain', description: 'The domain name.')]
  #[Option(name: 'domain-id', description: 'The domain ID.')]
  #[FieldLabels(
    labels:[
      'host-id' => 'The HID',
      'host-label' => 'The host label',
    ],
  )]
  #[DefaultFields(fields:['host-id', 'host-label'])]
  #[Usage(name: 'drush drd:list:hosts', description: 'Output a list of hosts with certain details.')]
  public function listHosts(
    array $options = [
      'tag' => NULL,
      'host' => NULL,
      'host-id' => NULL,
      'core' => NULL,
      'core-id' => NULL,
      'domain' => NULL,
      'domain-id' => NULL,
      'format' => 'table',
    ],
  ): RowsOfFields {
    $this->actionKey = 'drd_action_list_hosts';
    $this->options = $options;
    $this->setOutput(new NullOutput());
    $rows = $this
      ->prepare([], array_keys($options))
      ->execute();
    return new RowsOfFields($rows);
  }

  /**
   * Get or set the maintenance mode on/from remote domain(s).
   *
   * @param string $mode
   *   The mode for this command, you can turn on or off maintenance mode and
   *   you can get the current status.
   * @param array $options
   *   CLI options.
   */
  #[Command(name: 'drd:maintenancemode', aliases: ['drd-maintenancemode'])]
  #[Argument(name: 'mode', description: 'The mode for this command, you can turn on or off maintenance mode and you can get the current status.')]
  #[Option(name: 'tag', description: 'The tag name.')]
  #[Option(name: 'host', description: 'The host name.')]
  #[Option(name: 'host-id', description: 'The host ID.')]
  #[Option(name: 'core', description: 'The core name.')]
  #[Option(name: 'core-id', description: 'The core ID.')]
  #[Option(name: 'domain', description: 'The domain name.')]
  #[Option(name: 'domain-id', description: 'The domain ID.')]
  #[Usage(name: 'drush drd:maintenancemode', description: 'Get or set the maintenance mode on/from remote domain(s).')]
  public function maintenancemode(
    string $mode,
    array $options = [
      'tag' => NULL,
      'host' => NULL,
      'host-id' => NULL,
      'core' => NULL,
      'core-id' => NULL,
      'domain' => NULL,
      'domain-id' => NULL,
    ],
  ): void {
    $this->actionKey = 'drd_action_maintenance_mode';
    $this->options = $options;
    $this->entities = $this->service()->domains();
    $this
      ->prepare(['mode' => $mode])
      ->execute();
  }

  /**
   * Validation callback for the MaintenanceMode command.
   *
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   *   The command data to validate.
   *
   * @throws \Exception
   */
  #[Hook(type: HookManager::ARGUMENT_VALIDATOR, target: 'drd:maintenancemode')]
  public function validateMaintenanceMode(CommandData $commandData): void {
    $this->validateArguments($commandData, ['mode']);
  }

  /**
   * Execute arbitrary PHP code on remote domain(s).
   *
   * @param string $php
   *   Arbitrary PHP code to be executed.
   * @param array $options
   *   CLI options.
   */
  #[Command(name: 'drd:php', aliases: ['drd-php'])]
  #[Argument(name: 'php', description: 'Arbitrary PHP code to be executed.')]
  #[Option(name: 'tag', description: 'The tag name.')]
  #[Option(name: 'host', description: 'The host name.')]
  #[Option(name: 'host-id', description: 'The host ID.')]
  #[Option(name: 'core', description: 'The core name.')]
  #[Option(name: 'core-id', description: 'The core ID.')]
  #[Option(name: 'domain', description: 'The domain name.')]
  #[Option(name: 'domain-id', description: 'The domain ID.')]
  #[Usage(name: 'drush drd:php', description: 'Execute arbitrary PHP code on remote domain(s).')]
  public function php(
    string $php,
    array $options = [
      'tag' => NULL,
      'host' => NULL,
      'host-id' => NULL,
      'core' => NULL,
      'core-id' => NULL,
      'domain' => NULL,
      'domain-id' => NULL,
    ],
  ): void {
    $this->actionKey = 'drd_action_php';
    $this->options = $options;
    $this->entities = $this->service()->domains();
    $this
      ->prepare(['php' => $php])
      ->execute();
  }

  /**
   * Validation callback for the Php command.
   *
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   *   The command data to validate.
   *
   * @throws \Exception
   */
  #[Hook(type: HookManager::ARGUMENT_VALIDATOR, target: 'drd:php')]
  public function validatePhp(CommandData $commandData): void {
    $this->validateArguments($commandData, ['php']);
  }

  /**
   * Ping remote domain(s).
   *
   * @param array $options
   *   CLI options.
   */
  #[Command(name: 'drd:ping', aliases: ['drd-ping'])]
  #[Option(name: 'tag', description: 'The tag name.')]
  #[Option(name: 'host', description: 'The host name.')]
  #[Option(name: 'host-id', description: 'The host ID.')]
  #[Option(name: 'core', description: 'The core name.')]
  #[Option(name: 'core-id', description: 'The core ID.')]
  #[Option(name: 'domain', description: 'The domain name.')]
  #[Option(name: 'domain-id', description: 'The domain ID.')]
  #[Option(name: 'save', description: '')]
  #[Usage(name: 'drush drd:ping', description: 'Ping remote domain(s).')]
  public function ping(
    array $options = [
      'tag' => NULL,
      'host' => NULL,
      'host-id' => NULL,
      'core' => NULL,
      'core-id' => NULL,
      'domain' => NULL,
      'domain-id' => NULL,
      'save' => FALSE,
    ],
  ): void {
    $this->actionKey = 'drd_action_ping';
    $this->options = $options;
    $this->entities = $this->service()->domains();
    $this
      ->prepare([], ['save'])
      ->execute();
  }

  /**
   * Receive a list of projects used by remote domain(s).
   *
   * @param array $options
   *   CLI options.
   */
  #[Command(name: 'drd:projects:usage', aliases: ['drd-projects-usage'])]
  #[Option(name: 'tag', description: 'The tag name.')]
  #[Option(name: 'host', description: 'The host name.')]
  #[Option(name: 'host-id', description: 'The host ID.')]
  #[Option(name: 'core', description: 'The core name.')]
  #[Option(name: 'core-id', description: 'The core ID.')]
  #[Option(name: 'domain', description: 'The domain name.')]
  #[Option(name: 'domain-id', description: 'The domain ID.')]
  #[Usage(name: 'drush drd:projects:usage', description: 'Receive a list of projects used by remote domain(s).')]
  public function projectsUsage(
    array $options = [
      'tag' => NULL,
      'host' => NULL,
      'host-id' => NULL,
      'core' => NULL,
      'core-id' => NULL,
      'domain' => NULL,
      'domain-id' => NULL,
    ],
  ): void {
    $this->actionKey = 'drd_action_projects';
    $this->options = $options;
    $this->entities = $this->service()->domains();
    $this
      ->prepare()
      ->execute();
  }

  /**
   * Check update status for all projects being used by all remote domains.
   */
  #[Command(name: 'drd:projects:status', aliases: ['drd-projects-status'])]
  #[Usage(name: 'drush drd:projects:status', description: 'Check update status for all projects being used by all remote domains.')]
  public function projectsStatus(): void {
    $this->actionKey = 'drd_action_projects_status';
    $this
      ->prepare()
      ->execute();
  }

  /**
   * Update a complete Drupal installation of remote core(s).
   *
   * @param array $options
   *   CLI options.
   */
  #[Command(name: 'drd:projects:update', aliases: ['drd-projects-update'])]
  #[Option(name: 'tag', description: 'The tag name.')]
  #[Option(name: 'host', description: 'The host name.')]
  #[Option(name: 'host-id', description: 'The host ID.')]
  #[Option(name: 'core', description: 'The core name.')]
  #[Option(name: 'core-id', description: 'The core ID.')]
  #[Option(name: 'domain', description: 'The domain name.')]
  #[Option(name: 'domain-id', description: 'The domain ID.')]
  #[Option(name: 'dry-run', description: 'Perform the update in the working directory but do not commit, push or deploythe list with this option.')]
  #[Option(name: 'show-log', description: 'Show the log output.')]
  #[Option(name: 'list', description: 'Output a list of available updates.')]
  #[Option(name: 'security-only', description: 'Only include security updates.')]
  #[Option(name: 'force-locked-security', description: 'Always include security updates, even if locked.')]
  #[Usage(name: 'drush drd:projects:update', description: 'Update a complete Drupal installation of remote core(s).')]
  public function projectsUpdate(
    array $options = [
      'tag' => NULL,
      'host' => NULL,
      'host-id' => NULL,
      'core' => NULL,
      'core-id' => NULL,
      'domain' => NULL,
      'domain-id' => NULL,
      'dry-run' => FALSE,
      'show-log' => FALSE,
      'list' => FALSE,
      'include-locked' => FALSE,
      'security-only' => FALSE,
      'force-locked-security' => FALSE,
    ],
  ): void {
    $this->actionKey = 'drd_action_projects_update';
    $this->options = $options;
    $this->entities = $this->service()->cores();
    $this
      ->prepare([], [
        'dry-run',
        'show-log',
        'list',
        'include-locked',
        'security-only',
        'force-locked-security',
      ])
      ->execute();
  }

  /**
   * Display logs of core updates.
   *
   * @param array $options
   *   CLI options.
   */
  #[Command(name: 'drd:projects:update:log', aliases: ['drd-projects-update-log'])]
  #[Option(name: 'tag', description: 'The tag name.')]
  #[Option(name: 'host', description: 'The host name.')]
  #[Option(name: 'host-id', description: 'The host ID.')]
  #[Option(name: 'core', description: 'The core name.')]
  #[Option(name: 'core-id', description: 'The core ID.')]
  #[Option(name: 'domain', description: 'The domain name.')]
  #[Option(name: 'domain-id', description: 'The domain ID.')]
  #[Option(name: 'id', description: 'Shows the latest by default, you can get any of the other from the list with this option.')]
  #[Option(name: 'list', description: 'List the available logs.')]
  #[Usage(name: 'drush drd:projects:update:log', description: 'Display logs of core updates.')]
  public function projectsUpdateLog(
    array $options = [
      'tag' => NULL,
      'host' => NULL,
      'host-id' => NULL,
      'core' => NULL,
      'core-id' => NULL,
      'domain' => NULL,
      'domain-id' => NULL,
      'id' => NULL,
      'list' => FALSE,
    ],
  ): void {
    $this->actionKey = 'drd_action_projects_update_log';
    $this->options = $options;
    $this->entities = $this->service()->cores();
    $this
      ->prepare([], ['id', 'list'])
      ->execute();
  }

  /**
   * Lock a project release globally or for specific cores.
   *
   * @param string $projectName
   *   Name of the project to be locked.
   * @param string $version
   *   Version of the project release to be locked.
   * @param array $options
   *   CLI options.
   */
  #[Command(name: 'drd:release:lock', aliases: ['drd-release-lock'])]
  #[Argument(name: 'projectName', description: 'Name of the project to be locked.')]
  #[Argument(name: 'version', description: 'Version of the project release to be locked.')]
  #[Option(name: 'tag', description: 'The tag name.')]
  #[Option(name: 'host', description: 'The host name.')]
  #[Option(name: 'host-id', description: 'The host ID.')]
  #[Option(name: 'core', description: 'The core name.')]
  #[Option(name: 'core-id', description: 'The core ID.')]
  #[Option(name: 'domain', description: 'The domain name.')]
  #[Option(name: 'domain-id', description: 'The domain ID.')]
  #[Usage(name: 'drush drd:release:lock', description: 'Lock a project release globally or for specific cores.')]
  public function projectsReleaseLock(
    string $projectName,
    string $version,
    array $options = [
      'tag' => NULL,
      'host' => NULL,
      'host-id' => NULL,
      'core' => NULL,
      'core-id' => NULL,
      'domain' => NULL,
      'domain-id' => NULL,
    ],
  ): void {
    $this->actionKey = 'drd_action_release_lock';
    $this->options = $options;
    $service = $this->service();
    $criteria = $service->getSelectionCriteria();
    $this
      ->prepare([
        'projectName' => $projectName,
        'version' => $version,
        'cores' => empty($criteria) ? NULL : $service->cores(),
      ])
      ->execute();
  }

  /**
   * Unlock a project release globally or for specific cores.
   *
   * @param string $projectName
   *   Name of the project to be unlocked.
   * @param string $version
   *   Version of the project release to be unlocked.
   * @param array $options
   *   CLI options.
   */
  #[Command(name: 'drd:release:unlock', aliases: ['drd-release-unlock'])]
  #[Argument(name: 'projectName', description: 'Name of the project to be locked.')]
  #[Argument(name: 'version', description: 'Version of the project release to be locked.')]
  #[Option(name: 'tag', description: 'The tag name.')]
  #[Option(name: 'host', description: 'The host name.')]
  #[Option(name: 'host-id', description: 'The host ID.')]
  #[Option(name: 'core', description: 'The core name.')]
  #[Option(name: 'core-id', description: 'The core ID.')]
  #[Option(name: 'domain', description: 'The domain name.')]
  #[Option(name: 'domain-id', description: 'The domain ID.')]
  #[Usage(name: 'drush drd:release:unlock', description: 'Unlock a project release globally or for specific cores.')]
  public function projectsReleaseUnlock(
    string $projectName,
    string $version,
    array $options = [
      'tag' => NULL,
      'host' => NULL,
      'host-id' => NULL,
      'core' => NULL,
      'core-id' => NULL,
      'domain' => NULL,
      'domain-id' => NULL,
    ],
  ): void {
    $this->actionKey = 'drd_action_release_unlock';
    $this->options = $options;
    $service = $this->service();
    $criteria = $service->getSelectionCriteria();
    $this
      ->prepare([
        'projectName' => $projectName,
        'version' => $version,
        'cores' => empty($criteria) ? NULL : $service->cores(),
      ])
      ->execute();
  }

  /**
   * Receive a URL to start a session on remote domain(s).
   *
   * @param array $options
   *   CLI options.
   */
  #[Command(name: 'drd:session', aliases: ['drd-session'])]
  #[Option(name: 'tag', description: 'The tag name.')]
  #[Option(name: 'host', description: 'The host name.')]
  #[Option(name: 'host-id', description: 'The host ID.')]
  #[Option(name: 'core', description: 'The core name.')]
  #[Option(name: 'core-id', description: 'The core ID.')]
  #[Option(name: 'domain', description: 'The domain name.')]
  #[Option(name: 'domain-id', description: 'The domain ID.')]
  #[Usage(name: 'drush drd:session', description: 'Receive a URL to start a session on remote domain(s).')]
  public function session(
    array $options = [
      'tag' => NULL,
      'host' => NULL,
      'host-id' => NULL,
      'core' => NULL,
      'core-id' => NULL,
      'domain' => NULL,
      'domain-id' => NULL,
    ],
  ): void {
    $this->actionKey = 'drd_action_session';
    $this->options = $options;
    $this->entities = $this->service()->domains();
    $this
      ->prepare()
      ->execute();
  }

  /**
   * Run update.php on remote domain(s).
   *
   * @param array $options
   *   CLI options.
   */
  #[Command(name: 'drd:update', aliases: ['drd-update'])]
  #[Option(name: 'tag', description: 'The tag name.')]
  #[Option(name: 'host', description: 'The host name.')]
  #[Option(name: 'host-id', description: 'The host ID.')]
  #[Option(name: 'core', description: 'The core name.')]
  #[Option(name: 'core-id', description: 'The core ID.')]
  #[Option(name: 'domain', description: 'The domain name.')]
  #[Option(name: 'domain-id', description: 'The domain ID.')]
  #[Usage(name: 'drush drd:update', description: 'Run update.php on remote domain(s).')]
  public function update(
    array $options = [
      'tag' => NULL,
      'host' => NULL,
      'host-id' => NULL,
      'core' => NULL,
      'core-id' => NULL,
      'domain' => NULL,
      'domain-id' => NULL,
    ],
  ): void {
    $this->actionKey = 'drd_action_update';
    $this->options = $options;
    $this->entities = $this->service()->domains();
    $this
      ->prepare()
      ->execute();
  }

  /**
   * Change credentials of a user on remote domain(s).
   *
   * @param int $uid
   *   User id of the remote account which should be changed.
   * @param array $options
   *   CLI options.
   */
  #[Command(name: 'drd:user:credentials', aliases: ['drd-user-credentials'])]
  #[Argument(name: 'uid', description: 'User id of the remote account which should be changed.')]
  #[Option(name: 'tag', description: 'The tag name.')]
  #[Option(name: 'host', description: 'The host name.')]
  #[Option(name: 'host-id', description: 'The host ID.')]
  #[Option(name: 'core', description: 'The core name.')]
  #[Option(name: 'core-id', description: 'The core ID.')]
  #[Option(name: 'domain', description: 'The domain name.')]
  #[Option(name: 'domain-id', description: 'The domain ID.')]
  #[Option(name: 'username', description: 'The user name to be set.')]
  #[Option(name: 'password', description: 'The password to be set.')]
  #[Option(name: 'status', description: 'The status to be set.')]
  #[Usage(name: 'drush drd:user:credentials', description: 'Change credentials of a user on remote domain(s).')]
  public function userCredentials(
    int $uid,
    array $options = [
      'tag' => NULL,
      'host' => NULL,
      'host-id' => NULL,
      'core' => NULL,
      'core-id' => NULL,
      'domain' => NULL,
      'domain-id' => NULL,
      'username' => NULL,
      'password' => NULL,
      'status' => NULL,
    ],
  ): void {
    $this->actionKey = 'drd_action_user_credentials';
    $this->options = $options;
    $this->entities = $this->service()->domains();
    $this
      ->prepare(['uid' => $uid, ['username', 'password', 'status']])
      ->execute();
  }

  /**
   * Validation callback for the UserCredential command.
   *
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   *   The command data to validate.
   *
   * @throws \Exception
   */
  #[Hook(type: HookManager::ARGUMENT_VALIDATOR, target: 'drd:user:credentials')]
  public function validateUserCredentials(CommandData $commandData): void {
    $this->validateArguments($commandData, ['uid']);
  }

  /**
   * Remove unused releases from the local database.
   */
  #[Command(name: 'drd:cleanup:unused:releases', aliases: ['drd-cleanup-unused-releases'])]
  #[Usage(name: 'drush drd:cleanup:unused:releases', description: 'Remove unused releases from the local database.')]
  public function cleanupUnusedReleases(): void {
    $this->cleanup->cleanupReleases();
  }

  /**
   * Remove unused major versions from the local database.
   */
  #[Command(name: 'drd:cleanup:unused:majors', aliases: ['drd-cleanup-unused-majors'])]
  #[Usage(name: 'drush drd:cleanup:unused:majors', description: 'Remove unused major versions from the local database.')]
  public function cleanupUnusedMajors(): void {
    $this->cleanup->cleanupMajors();
  }

  /**
   * Remove unused projects from the local database.
   */
  #[Command(name: 'drd:cleanup:unused:projects', aliases: ['drd-cleanup-unused-projects'])]
  #[Usage(name: 'drush drd:cleanup:unused:projects', description: 'Remove unused projects from the local database.')]
  public function cleanupUnusedProjects(): void {
    $this->cleanup->cleanupProjects();
  }

  /**
   * Remove all projects, major versions and releases from the local database.
   */
  #[Command(name: 'drd:reset:all:projects:data', aliases: ['drd-reset-all-projects-data'])]
  #[Usage(name: 'drush drd:reset:all:projects:data', description: 'Remove all projects, major versions and releases from the local database.')]
  public function resetAllProjectsData(): void {
    $this->cleanup->resetAll();
  }

}
