<?php

namespace Drupal\drd_pi;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Config\ImmutableConfig;
use mikehaertl\shellcommand\Command as ShellCommand;

/**
 * Provides an interface for defining Account entities.
 */
abstract class DrdPiAccount extends ConfigEntityBase implements DrdPiAccountInterface {

  /**
   * Plugin ID of the DrdPiAccount.
   *
   * @var string
   */
  protected string $id;

  /**
   * Label of the DrdPiAccount.
   *
   * @var string
   */
  protected string $label;

  /**
   * Logging service for output.
   *
   * @var \Drupal\drd\Logging
   */
  protected mixed $logging;

  /**
   * Output of the last run shell command.
   *
   * @var string
   */
  protected string $lastShellOutput;

  /**
   * List of DrdPiHosts.
   *
   * @var DrdPiHost[]
   */
  protected array $hosts;

  /**
   * List of DrdPiCores.
   *
   * @var DrdPiCore[]
   */
  protected array $cores;

  /**
   * List of DrdPiDomains.
   *
   * @var DrdPiDomain[]
   */
  protected array $domains;

  /**
   * Configuration of the acocunt plugin.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * The http client factory.
   *
   * @var \Drupal\Core\Http\ClientFactory
   */
  protected mixed $httpClientFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $values, $entity_type) {
    parent::__construct($values, $entity_type);
    $this->logging = \Drupal::service('drd.logging');
    /* @noinspection StaticInvocationViaThisInspection */
    $this->config = \Drupal::config($this->getConfigName());
    $this->httpClientFactory = \Drupal::service('http_client_factory');
  }

  /**
   * Decrypt and return the value of $key.
   *
   * @param string $key
   *   The name of the field for which to retrieve the value.
   *
   * @return string|null
   *   The decrypted value.
   */
  protected function getDecrypted(string $key): ?string {
    $value = $this->get($key);
    if ($value !== NULL) {
      \Drupal::service('drd.encrypt')->decrypt($value);
    }
    return $value;
  }

  /**
   * Encrypt and set the value of $key.
   *
   * @param string $key
   *   The name of the field for which to set the value.
   * @param string $value
   *   The value of the field.
   *
   * @return $this
   */
  protected function setEncrypted(string $key, string $value): self {
    \Drupal::service('drd.encrypt')->encrypt($value);
    $this->set($key, $value);
    return $this;
  }

  /**
   * Add new entities and enable/disable existing ones to match imventory.
   *
   * @param DrdPiEntityInterface[] $platform
   *   List of DrdPiEntities as they exist on the platform, the inventory.
   * @param string $type
   *   Type is either core, host or domain.
   * @param DrdPiEntityInterface|null $parent
   *   The optional parent entity to which the list of entities are attached.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function syncEntities(array $platform, string $type, ?DrdPiEntityInterface $parent = NULL): void {

    // Get all internal entities.
    $properties = [
      'pi_type' => $this->entityTypeId,
      'pi_account' => $this->id(),
    ];
    if ($parent !== NULL) {
      switch ($type) {
        case 'core':
          $properties['pi_id_host'] = $parent->id();
          break;

        case 'domain':
          $properties['pi_id_host'] = $parent->host()->id();
          $properties['pi_id_core'] = $parent->id();
          break;

      }
    }
    $storage = $this->entityTypeManager()->getStorage('drd_' . $type);
    /** @var \Drupal\drd\Entity\BaseInterface[] $internal */
    $internal = $storage->loadByProperties($properties);

    $ids_with_pi = [];

    // Work through all platform entities.
    foreach ($platform as $entity) {

      $this->logging->debug('Checking @label', ['@label' => $entity->label()]);
      // Check if we already know that platform entity.
      foreach ($internal as $drd_entity) {
        if (!in_array($drd_entity->id(), $ids_with_pi, TRUE) && drd_pi_get_entity_value($drd_entity, $type) === $entity->id()) {
          $this->logging->debug('- already available');
          $entity->setDrdEntity($drd_entity);
          $ids_with_pi[] = $drd_entity->id();
          break;
        }
      }

      // Create new DRD entity if don't have it yet.
      if (!$entity->hasDrdEntity()) {
        $this->logging->debug('- create');
        $entity->create();
      }

      $entity->update();
    }

    // Enable/disable DRD entities that no longer exist on the platform.
    foreach ($internal as $drd_entity) {
      $status = in_array($drd_entity->id(), $ids_with_pi, TRUE);
      if ($drd_entity->isPublished() !== $status) {
        $this->logging->debug('@action @type @label', [
          '@action' => ($status ? 'Re-enable' : 'Disable'),
          '@type' => $type,
          '@label' => $drd_entity->label(),
        ]);
        $drd_entity
          ->setPublished($status)
          ->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function sync(): DrdPiAccountInterface {
    $this->logging->log('info', 'Receiving hosts');
    $hosts = $this->getPlatformHosts();
    $this->logging->log('info', 'Syncing hosts');
    $this->syncEntities($hosts, 'host');

    foreach ($hosts as $host) {
      $this->logging->log('info', 'Receiving cores for host @label', ['@label' => $host->label()]);
      $cores = $this->getPlatformCores($host);
      $this->logging->log('info', 'Syncing cores');
      $this->syncEntities($cores, 'core', $host);

      foreach ($cores as $core) {
        $this->logging->log('info', 'Receiving domains for core @label', ['@label' => $core->label()]);
        $domains = $this->getPlatformDomains($core);
        $this->logging->log('info', 'Syncing domains');
        $this->syncEntities($domains, 'domain', $core);
      }
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlatformDomains(DrdPiCore $core): array {
    $this->domains = [];

    if (isset($this->cores[$core->id()])) {
      $this->domains = $this->cores[$core->id()]->getDomains();
    }
    return $this->domains;
  }

  /**
   * Execute a shell command, capture the console output and return exit code.
   *
   * @param string $cmd
   *   The command to be executed.
   *
   * @return int
   *   Exit code of the executed command.
   */
  public function shell(string $cmd): int {
    $this->lastShellOutput = '';
    $command = new ShellCommand($cmd);
    $command->execute();
    $this->lastShellOutput = $command->getOutput();
    return $command->getExitCode();
  }

}
