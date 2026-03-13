<?php

namespace Drupal\drd;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ExtensionList;
use Drupal\Core\File\FileSystemInterface;
use Drupal\drd\Entity\DomainInterface;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader;

/**
 * Build a local copy of a domain into a given working directory.
 */
class DomainLocalCopy {

  /**
   * DRD settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $drdConfig;

  /**
   * Decrypted local db password.
   *
   * @var string
   */
  protected string $localDbPass;

  /**
   * Logging progress.
   *
   * @var string[]
   */
  protected array $log = [];

  /**
   * Drupal root directory.
   *
   * @var string
   */
  protected string $drupalDir;

  /**
   * Project root directory.
   *
   * @var string
   */
  protected string $workingDir;

  /**
   * Project settings directory.
   *
   * @var string
   */
  protected string $settingsDir;

  /**
   * DRD domain entity for which to create a local copy.
   *
   * @var \Drupal\drd\Entity\DomainInterface
   */
  protected DomainInterface $domain;

  /**
   * All domain GLOBALS.
   *
   * @var array
   */
  protected array $domainGlobals;

  /**
   * All domain settings.
   *
   * @var array
   */
  protected array $domainSettings;

  /**
   * Drupal core version of the domain.
   *
   * @var int
   */
  protected int $coreVersion;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The encryption service.
   *
   * @var \Drupal\drd\Encryption
   */
  protected Encryption $encryption;

  /**
   * The extension list.
   *
   * @var \Drupal\Core\Extension\ExtensionList
   */
  protected ExtensionList $extensionList;

  /**
   * DomainLocalCopy constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\drd\Encryption $encryption
   *   The encryption service.
   * @param \Drupal\Core\Extension\ExtensionList $extensionList
   *   The extension list.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, FileSystemInterface $file_system, ConfigFactoryInterface $config_factory, Encryption $encryption, ExtensionList $extensionList) {
    $this->entityTypeManager = $entity_type_manager;
    $this->fileSystem = $file_system;
    $this->configFactory = $config_factory;
    $this->encryption = $encryption;
    $this->extensionList = $extensionList;
  }

  /**
   * Get the activity log.
   *
   * @return string[]
   *   The activity log.
   */
  public function getLog(): array {
    return $this->log;
  }

  /**
   * Set the Drupal root directory.
   *
   * @param string $drupalDir
   *   Drupal root directory.
   *
   * @return $this
   */
  public function setDrupalDirectory(string $drupalDir): self {
    $this->drupalDir = $drupalDir;
    return $this;
  }

  /**
   * Set the project root directory.
   *
   * @param string $workingDir
   *   The root directory.
   *
   * @return $this
   */
  public function setWorkingDirectory(string $workingDir): self {
    $this->workingDir = $workingDir;
    return $this;
  }

  /**
   * Set the DRD domain entity.
   *
   * @param \Drupal\drd\Entity\DomainInterface $domain
   *   The domain entity.
   *
   * @return $this
   */
  public function setDomain(DomainInterface $domain): self {
    $this->drdConfig = $this->configFactory->get('drd.general');
    $this->localDbPass = $this->drdConfig->get('local.db.pass');
    $this->encryption->decrypt($this->localDbPass);
    $this->domain = $domain;
    $this->domainGlobals = $this->domain->getRemoteGlobals();
    $this->domainSettings = $this->domain->getRemoteSettings();
    $this->coreVersion = $this->domain->getCore()->getDrupalRelease()->getMajor()->getCoreVersion();
    $this->settingsDir = implode(DIRECTORY_SEPARATOR, [
      $this->drupalDir,
      'sites',
      $this->domain->getLocalUrl(),
    ]);

    // Create or update sites.php.
    $sites_file = implode(DIRECTORY_SEPARATOR, [
      $this->drupalDir,
      'sites',
      'sites.php',
    ]);
    if (!file_exists($sites_file)) {
      file_put_contents($sites_file, '<?php');
    }
    file_put_contents(
      $sites_file,
      PHP_EOL . '$sites["' . $this->domain->getLocalUrl() . '"] = "' . $this->domain->getLocalUrl() . '";',
      FILE_APPEND
    );

    return $this;
  }

  /**
   * Setup the local copy when everything else has been prepared.
   *
   * @return bool
   *   TRUE if the copy was created successfully.
   */
  public function setup(): bool {
    $databases = $this->domain->database();
    if (empty($databases)) {
      return FALSE;
    }
    foreach ($databases as $key => $targets) {
      foreach ($targets as $target => $def) {
        $databases[$key][$target]['database'] = $this->buildDatabase($key, $target, $def);
      }
    }

    $options = [
      'drd' => [
        'db' => [
          'user' => $this->drdConfig->get('local.db.user'),
          'pass' => $this->localDbPass,
        ],
      ],
      'databases' => $databases,
      'database_config' => Database::getConnectionInfo()['default'],
      'globals' => $this->domainGlobals,
      'settings' => $this->domainSettings,
      'tempdir' => $this->fileSystem->realpath($this->fileSystem->getTempDirectory()),
      'url' => $this->domain->getLocalUrl(),
    ];

    $this->mkdir($this->settingsDir);

    $templatefilename = $this->extensionList->getPath('drd') . '/templates/DomainLocalCopy.v' . $this->coreVersion . '.settings.php.twig';

    $template = 'settings';
    $twig_loader = new ArrayLoader([]);
    $twig = new Environment($twig_loader);
    $twig_loader->setTemplate($template, file_get_contents($templatefilename));
    $rendered = '';
    try {
      $rendered = $twig->render($template, $options);
    }
    catch (LoaderError | RuntimeError | SyntaxError) {
      // @todo Log this exception.
    }
    file_put_contents($this->settingsDir . DIRECTORY_SEPARATOR . 'settings.php', $rendered);

    $this->mkdir($this->drupalDir . DIRECTORY_SEPARATOR . $this->domainSettings['file_public_path']);
    $this->mkdir($this->drupalDir . DIRECTORY_SEPARATOR . $this->domainSettings['file_private_path']);
    if (!isset($this->domainGlobals['config_directories'])) {
      // This is for Drupal 9.
      $this->domainGlobals['config_directories'] = ['sites/default/files/config/sync'];
    }
    foreach ($this->domainGlobals['config_directories'] as $config_directory) {
      $this->mkdir($this->drupalDir . DIRECTORY_SEPARATOR . $config_directory);
    }
    return TRUE;
  }

  /**
   * Remove temporary databases again.
   */
  public function dropDatabases(): void {
    $databases = $this->domain->database();
    if (empty($databases)) {
      return;
    }
    foreach ($databases as $key => $targets) {
      foreach ($targets as $target => $def) {
        $this->buildDatabase($key, $target, $def, TRUE);
      }
    }
  }

  /**
   * Create a directory taking care of symbolic links.
   *
   * @param string $dir
   *   Name of the directory.
   *
   * @throws \RuntimeException
   */
  private function mkdir(string $dir): void {
    $fs = new Filesystem();
    if ($fs->exists($dir)) {
      if (is_dir($dir) || is_link($dir)) {
        return;
      }
      throw new \RuntimeException('Can not create directory ' . $dir);
    }
    if (is_link($dir)) {
      if (($target = readlink($dir)) && $target[0] === DIRECTORY_SEPARATOR) {
        $dir = $target;
      }
      else {
        $startparts = explode(DIRECTORY_SEPARATOR, $dir);
        $targetparts = explode(DIRECTORY_SEPARATOR, $target);
        array_unshift($targetparts, '..');
        while ($targetparts[0] === '..') {
          array_shift($targetparts);
          array_pop($startparts);
        }
        $parts = array_merge($startparts, $targetparts);
        $dir = implode(DIRECTORY_SEPARATOR, $parts);
      }
      $this->mkdir($dir);
      return;
    }
    $fs->mkdir($dir);
  }

  /**
   * Create and import a temporary database.
   *
   * @param string $key
   *   The database key.
   * @param string $target
   *   The database target.
   * @param array $def
   *   The database definition/configuration.
   * @param bool $drop
   *   Set to TRUE to drop the database again.
   *
   * @return string
   *   Name of the temporary database.
   */
  private function buildDatabase(string $key, string $target, array $def, bool $drop = FALSE): string {
    $database = implode('_', [
      'drd',
      'dump',
      $this->domain->id(),
      $key,
      $target,
    ]);
    $output = [];

    $config = Database::getConnectionInfo()['default'];
    $credentialsfile = $this->fileSystem->realpath($this->fileSystem->tempnam('temporary://', 'mysql'));

    $cmd = [
      'mysql',
      '--defaults-extra-file=' . $credentialsfile,
    ];
    $credentials = [
      '[mysql]',
      'host = ' . $config['host'],
      'port = ' . $config['port'],
      'user = ' . $this->drdConfig->get('local.db.user'),
      'password = ' . $this->localDbPass,
    ];

    file_put_contents($credentialsfile, implode("\n", $credentials));
    chmod($credentialsfile, 0600);

    $instruction = $drop ? '' : '; CREATE DATABASE ' . $database;
    $prepare = array_merge($cmd, [
      '--execute="DROP DATABASE IF EXISTS ' . $database . $instruction . ';"',
    ]);
    exec(implode(' ', $prepare), $output, $ret1);
    if ($ret1 !== 0) {
      $output[] = $drop ?
        'Can not drop database' :
        'Can not prepare database.';
    }
    elseif (!$drop) {
      $import = array_merge($cmd, [
        $database,
        '<' . $def['file'],
      ]);
      exec(implode(' ', $import), $output, $ret2);
      if ($ret2 !== 0) {
        $output[] = 'Can not import database.';
      }
    }

    unlink($credentialsfile);

    foreach ($output as $item) {
      $this->log[] = $item;
    }

    return $database;
  }

}
