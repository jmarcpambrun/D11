<?php

namespace Drupal\drd\Plugin\Update;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\drd\DomainLocalCopy;
use Drupal\drd\Entity\Script;
use Drupal\drd\Update\PluginInterface;
use Drupal\drd\Update\PluginStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use mikehaertl\shellcommand\Command as ShellCommand;

/**
 * Abstract DRD Update plugin to implement general functionality.
 */
abstract class UpdateBase extends PluginBase implements PluginInterface, ContainerFactoryPluginInterface {

  /**
   * The id of the parent form element.
   *
   * @var string
   */
  protected string $configFormParent;

  /**
   * List of conditions for element visibility.
   *
   * @var array
   */
  protected array $condition;

  /**
   * Most recent shell output for logging.
   *
   * @var string
   */
  protected string $lastShellOutput = '';

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The service for local domain copies of DRD domains.
   *
   * @var \Drupal\drd\DomainLocalCopy
   */
  protected DomainLocalCopy $domainLocalCopy;

  /**
   * The http client factory.
   *
   * @var \Drupal\Core\Http\ClientFactory
   */
  protected ClientFactory $httpClientFactory;

  /**
   * {@inheritdoc}
   */
  final public function __construct(array $configuration, $plugin_id, $plugin_definition, FileSystemInterface $file_system, TimeInterface $time, DateFormatterInterface $date_formatter, ConfigFactoryInterface $config_factory, DomainLocalCopy $domain_local_copy, ClientFactory $client_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->fileSystem = $file_system;
    $this->time = $time;
    $this->dateFormatter = $date_formatter;
    $this->configFactory = $config_factory;
    $this->domainLocalCopy = $domain_local_copy;
    $this->httpClientFactory = $client_factory;

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): UpdateBase {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('file_system'),
      $container->get('datetime.time'),
      $container->get('date.formatter'),
      $container->get('config.factory'),
      $container->get('drd_domain.local_copy'),
      $container->get('http_client_factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  final public function setConfigFormContext(string $parent, array $condition): PluginInterface {
    $this->configFormParent = $parent;
    $this->condition = $condition;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  final public function getConfiguration(): array {
    return NestedArray::mergeDeep($this->defaultConfiguration(), $this->configuration);
  }

  /**
   * {@inheritdoc}
   */
  final public function setConfiguration(array $configuration): static {
    $this->configuration = NestedArray::mergeDeep($this->defaultConfiguration(), $configuration);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function scriptHooks(): array {
    return [
      'prePlugin' => $this->t('Before this plugin'),
      'postPlugin' => $this->t('After this plugin'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    $config = [
      'scripts' => [],
    ];
    foreach ($this->scriptHooks() as $scriptHook => $label) {
      $config['scripts'][$scriptHook] = '';
    }
    return $config;
  }

  /**
   * Get the Update plugin type.
   *
   * @return string
   *   The Update plugin type.
   */
  protected function getType(): string {
    $parts = explode('\\', get_class($this));
    array_pop($parts);
    return strtolower(array_pop($parts));
  }

  /**
   * Get the value of a form element.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return mixed
   *   The value of the form element.
   */
  protected function getFormValue(FormStateInterface $form_state): mixed {
    $args = func_get_args();
    array_shift($args);
    array_unshift($args, $this->pluginId);
    array_unshift($args, $this->getType());
    return $form_state->getValue($args);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $element = [];

    $element['scripts'] = [
      '#type' => 'details',
      '#title' => $this->t('Scripts'),
      '#open' => FALSE,
      '#weight' => 99,
    ];
    foreach ($this->scriptHooks() as $scriptHook => $label) {
      $element['scripts'][$scriptHook] = [
        '#type' => 'select',
        '#title' => $label,
        '#options' => Script::getSelectList(),
        '#default_value' => $this->configuration['scripts'][$scriptHook],
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    foreach ($this->scriptHooks() as $scriptHook => $label) {
      $value = $this->getFormValue($form_state, 'scripts', $scriptHook);
      if (!empty($value) && !file_exists($value)) {
        $form_state->setError($form[$this->configFormParent][$this->getType()][$this->pluginId]['scripts'][$scriptHook], $this->t('Script not found.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    foreach ($this->scriptHooks() as $scriptHook => $label) {
      $this->configuration['scripts'][$scriptHook] = $this->getFormValue($form_state, 'scripts', $scriptHook);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function cleanup(PluginStorageInterface $storage): PluginInterface {
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  final public function executeScript(PluginStorageInterface $storage, string $hook): PluginInterface {
    if ($hook === 'prePlugin' || $hook === 'postPlugin') {
      $action = ($hook === 'prePlugin') ? 'Start' : 'Finish';
      $def = $this->getPluginDefinition();
      $storage->log($action . ' step ' . $this->getType() . ' with plugin ' . $def['admin_label']);
    }
    if (!empty($this->configuration['scripts'][$hook])) {
      if ($script = Script::load($this->configuration['scripts'][$hook])) {
        $storage->log('Start script ' . $hook . ': ' . $script->label());
        try {
          $script->execute([
            'storage' => $storage,
            'hook' => $hook,
          ], $storage->getWorkingDirectory());
        }
        catch (\Exception) {
          // @todo Log this exception.
        }
        $output = $script->getOutput();
        if (!empty($output)) {
          $storage->log($output);
        }
      }
      $storage->log('Finish script ' . $hook);
    }
    return $this;
  }

  /**
   * Execute a shell command and capture the output.
   *
   * @param \Drupal\drd\Update\PluginStorageInterface $storage
   *   The update storage plugin.
   * @param string $cmd
   *   The command to execute.
   * @param string|null $workingDir
   *   The optional working directory where the command should be executed.
   *
   * @return int
   *   The exit code of the command.
   */
  protected function shell(PluginStorageInterface $storage, string $cmd, ?string $workingDir = NULL): int {
    $this->lastShellOutput = '';
    if (!isset($workingDir)) {
      $workingDir = $storage->getWorkingDirectory();
    }
    $fs = new Filesystem();
    if (!$fs->exists($workingDir)) {
      $fs->mkdir($workingDir);
    }
    $storage->log('Shell command [' . $workingDir . ']: ' . $cmd);
    $command = new ShellCommand($cmd);
    $command->procCwd = $workingDir;
    $success = $command->execute();
    $this->lastShellOutput = $command->getOutput();
    $storage->log($this->lastShellOutput);
    if (!$success) {
      $storage->log('[Error]: ' . $command->getError());
    }
    return $command->getExitCode();
  }

}
