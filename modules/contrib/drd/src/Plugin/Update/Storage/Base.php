<?php

namespace Drupal\drd\Plugin\Update\Storage;

use Drupal\Core\Form\FormStateInterface;
use Drupal\drd\Entity\CoreInterface;
use Drupal\drd\Plugin\Update\UpdateBase;
use Drupal\drd\Update\PluginBuildInterface;
use Drupal\drd\Update\PluginDeployInterface;
use Drupal\drd\Update\PluginFinishInterface;
use Drupal\drd\Update\PluginInterface;
use Drupal\drd\Update\PluginProcessInterface;
use Drupal\drd\Update\PluginStorageInterface;
use Drupal\drd\Update\PluginTestInterface;

/**
 * Abstract DRD Update plugin to implement general functionality.
 */
abstract class Base extends UpdateBase implements PluginStorageInterface {

  /**
   * The Drupal root directory.
   *
   * @var string
   */
  protected string $drupalDirectory;

  /**
   * The project's root directory.
   *
   * @var string|null
   */
  protected ?string $workingDirectory = NULL;

  /**
   * The full log text for the full update process.
   *
   * @var string
   */
  private string $logText = '';

  /**
   * The build plugin.
   *
   * @var \Drupal\drd\Update\PluginBuildInterface
   */
  private PluginBuildInterface $buildPlugin;

  /**
   * The processing plugin.
   *
   * @var \Drupal\drd\Update\PluginProcessInterface
   */
  private PluginProcessInterface $processPlugin;

  /**
   * The test plugin.
   *
   * @var \Drupal\drd\Update\PluginTestInterface
   */
  private PluginTestInterface $testPlugin;

  /**
   * The deploy plugin.
   *
   * @var \Drupal\drd\Update\PluginDeployInterface
   */
  private PluginDeployInterface $deployPlugin;

  /**
   * The finish plugin.
   *
   * @var \Drupal\drd\Update\PluginFinishInterface
   */
  private PluginFinishInterface $finishPlugin;

  /**
   * The core entity which will get updated.
   *
   * @var \Drupal\drd\Entity\CoreInterface
   */
  private CoreInterface $core;

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $element = parent::buildConfigurationForm($form, $form_state);

    $element['drupalroot'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Drupal Root'),
      '#default_value' => $this->configuration['drupalroot'],
      '#description' => $this->t('Relative path to Drupal root directory from the working directory without leading or trailing slash.'),
      '#weight' => 80,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['drupalroot'] = trim($this->getFormValue($form_state, 'drupalroot'), '/');
  }

  /**
   * {@inheritdoc}
   */
  final public function stepPlugins(
    PluginBuildInterface $build,
    PluginProcessInterface $process,
    PluginTestInterface $test,
    PluginDeployInterface $deploy,
    PluginFinishInterface $finish,
  ): PluginStorageInterface {
    $this->buildPlugin = $build;
    $this->processPlugin = $process;
    $this->testPlugin = $test;
    $this->deployPlugin = $deploy;
    $this->finishPlugin = $finish;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function scriptHooks(): array {
    return [
      'preUpdate' => $this->t('At the very beginning'),
      'postUpdate' => $this->t('At the very end'),
      'prePrepare' => $this->t('Before preparing working directory'),
      'postPrepare' => $this->t('After preparing working directory'),
      'preSave' => $this->t('Before saving working directory'),
      'postSave' => $this->t('After saving working directory'),
    ] + parent::scriptHooks();
  }

  /**
   * {@inheritdoc}
   */
  final public function execute(CoreInterface $core, array $releases, bool $dry, bool $showlog): bool|string {
    $this->core = $core;
    $result = TRUE;
    try {
      $this
        ->log('Start')
        ->executeScript($this, 'preUpdate')
        ->executeScript($this, 'prePlugin')
        ->setWorkingDirectory()
        ->executeScript($this, 'prePrepare')
        ->prepareWorkingDirectory()
        ->executeScript($this, 'postPrepare');
      $this->buildPlugin
        ->executeScript($this, 'prePlugin')
        ->build($this, $releases)
        ->patch($this)
        ->executeScript($this, 'postPlugin');
      if ($this->buildPlugin->hasChanged()) {
        $this->processPlugin
          ->executeScript($this, 'prePlugin')
          ->process($this)
          ->executeScript($this, 'postPlugin');
      }
      if ($this->processPlugin->hasSucceeded()) {
        $this->testPlugin
          ->executeScript($this, 'prePlugin')
          ->test($this)
          ->executeScript($this, 'postPlugin');
      }
      if ($dry) {
        if ($this->testPlugin->hasSucceeded()) {
          $this->deployPlugin
            ->executeScript($this, 'prePlugin')
            ->dryRun($this)
            ->executeScript($this, 'postPlugin');
        }
        if ($this->deployPlugin->hasSucceeded()) {
          $this->finishPlugin
            ->executeScript($this, 'prePlugin')
            ->dryRun($this)
            ->executeScript($this, 'postPlugin');
        }
      }
      else {
        if ($this->testPlugin->hasSucceeded()) {
          $this->deployPlugin
            ->executeScript($this, 'prePlugin')
            ->deploy($this)
            ->executeScript($this, 'postPlugin');
        }
        if ($this->deployPlugin->hasSucceeded()) {
          $this->finishPlugin
            ->executeScript($this, 'prePlugin')
            ->finish($this)
            ->executeScript($this, 'postPlugin');
        }
      }
    }
    catch (\Exception $ex) {
      $result = 'Exception: ' . $ex->getMessage();
      $this->log($result);
    }

    if ($dry) {
      $this->log('Finished dry');
    }
    else {
      try {
        $this->finishPlugin->cleanup($this);
        $this->deployPlugin->cleanup($this);
        $this->testPlugin->cleanup($this);
        $this->processPlugin->cleanup($this);
        $this->buildPlugin->cleanup($this);
        if ($result === TRUE) {
          $this
            ->executeScript($this, 'preSave')
            ->saveWorkingDirectory()
            ->executeScript($this, 'postSave');
        }
        $this
          ->cleanup($this)
          ->executeScript($this, 'postPlugin')
          ->executeScript($this, 'postUpdate');
        $this->log('Finish');
      }
      catch (\Exception $ex) {
        $result = 'Exception during save and cleanup: ' . $ex->getMessage();
        $this->log($result);
      }
    }

    if ($showlog) {
      print($this->logText);
    }
    $this->core->saveUpdateLog($this->logText);
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  final public function log(array|string $log): PluginStorageInterface {
    $logs = is_string($log) ? [$log] : $log;
    foreach ($logs as $line) {
      if (!empty(trim($line))) {
        $t = $this->dateFormatter->format(time(), 'custom', 'Y-m-d H:i:s');
        $this->logText .= '[' . $t . '] ' . str_replace("\n", "    \n", $line) . "\n";
      }
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  final public function getCore(): CoreInterface {
    return $this->core;
  }

  /**
   * {@inheritdoc}
   */
  public function getDrupalDirectory(): string {
    return empty($this->configuration['drupalroot']) ?
      $this->workingDirectory :
      $this->workingDirectory . DIRECTORY_SEPARATOR . $this->configuration['drupalroot'];
  }

  /**
   * {@inheritdoc}
   */
  final public function getWorkingDirectory(): string {
    return $this->workingDirectory;
  }

  /**
   * {@inheritdoc}
   */
  final public function getBuildPlugin(): PluginBuildInterface {
    return $this->buildPlugin;
  }

  /**
   * {@inheritdoc}
   */
  final public function getProcessPlugin(): PluginProcessInterface {
    return $this->processPlugin;
  }

  /**
   * {@inheritdoc}
   */
  final public function getTestPlugin(): PluginTestInterface {
    return $this->testPlugin;
  }

  /**
   * {@inheritdoc}
   */
  final public function getDeployPlugin(): PluginDeployInterface {
    return $this->deployPlugin;
  }

  /**
   * {@inheritdoc}
   */
  final public function getFinishPlugin(): PluginFinishInterface {
    return $this->finishPlugin;
  }

  /**
   * {@inheritdoc}
   */
  public function setWorkingDirectory(): PluginStorageInterface {
    if ($this->workingDirectory !== NULL) {
      $this->workingDirectory = $this->fileSystem->tempnam($this->fileSystem->getTempDirectory(), 'drd-update-');
      $this->drupalDirectory = $this->workingDirectory . DIRECTORY_SEPARATOR . $this->configuration['drupalroot'];
      $this->fileSystem->delete($this->workingDirectory);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareWorkingDirectory(): PluginStorageInterface {
    if (is_dir($this->workingDirectory)) {
      $this->fileSystem->deleteRecursive($this->workingDirectory);
    }
    elseif (file_exists($this->workingDirectory)) {
      $this->fileSystem->delete($this->workingDirectory);
    }
    $this->fileSystem->mkdir($this->workingDirectory);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function saveWorkingDirectory(): PluginStorageInterface {
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function cleanup(PluginStorageInterface $storage): PluginInterface {
    if (is_dir($this->workingDirectory)) {
      $this->fileSystem->deleteRecursive($this->workingDirectory, [
        self::class,
        'rmLink',
      ]);
    }
    return $this;
  }

  /**
   * Helper function for deleteRecursive to also remove symbolic links.
   *
   * @param string $path
   *   The file path, directory or link.
   */
  public static function rmLink(string $path): void {
    if (is_link($path)) {
      unlink($path);
      touch($path);
    }
  }

}
