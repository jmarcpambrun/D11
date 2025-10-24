<?php

namespace Drupal\drd\Update;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Manages discovery and instantiation of DRD Update Storage plugins.
 */
class ManagerStorage extends Manager implements ManagerStorageInterface {

  /**
   * The build manager.
   *
   * @var \Drupal\drd\Update\ManagerBuild
   */
  protected ManagerBuild $build;

  /**
   * The process manager.
   *
   * @var \Drupal\drd\Update\ManagerProcess
   */
  protected ManagerProcess $process;

  /**
   * The test manager.
   *
   * @var \Drupal\drd\Update\ManagerTests
   */
  protected ManagerTests $test;

  /**
   * The deploy manager.
   *
   * @var \Drupal\drd\Update\ManagerDeploy
   */
  protected ManagerDeploy $deploy;

  /**
   * The finish manager.
   *
   * @var \Drupal\drd\Update\ManagerFinish
   */
  protected ManagerFinish $finish;

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, ManagerBuild $build, ManagerProcess $process, ManagerTests $test, ManagerDeploy $deploy, ManagerFinish $finish) {
    parent::__construct($namespaces, $cache_backend, $module_handler);
    // Setting this again, since PHPStan otherwise complains.
    $this->setCacheBackend($cache_backend, 'drd_update_plugins_' . $this->getType());
    $this->build = $build;
    $this->process = $process;
    $this->test = $test;
    $this->deploy = $deploy;
    $this->finish = $finish;
  }

  /**
   * List of plugins.
   *
   * @var array
   */
  private array $plugins = [];

  /**
   * Get meta data for update plugin types.
   */
  private function meta(): array {
    return [
      'storage' => [
        'label' => t('Storage'),
        'manager' => $this,
      ],
      'build' => [
        'label' => t('Build'),
        'manager' => $this->build,
      ],
      'process' => [
        'label' => t('Process'),
        'manager' => $this->process,
      ],
      'test' => [
        'label' => t('Test'),
        'manager' => $this->test,
      ],
      'deploy' => [
        'label' => t('Deployment'),
        'manager' => $this->deploy,
      ],
      'finish' => [
        'label' => t('Finishing'),
        'manager' => $this->finish,
      ],
    ];
  }

  /**
   * Generate and return an update plugin instance.
   *
   * @param string $type
   *   The update plugin type.
   * @param array $settings
   *   The plugin settings.
   *
   * @return \Drupal\drd\Update\PluginInterface
   *   The update plugin.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  private function instance(string $type, array $settings): PluginInterface {
    $id = $settings['current'][$type];
    $meta = $this->meta();
    /** @var ManagerInterface $manager */
    $manager = $meta[$type]['manager'];
    /** @var \Drupal\drd\Update\PluginInterface $instance */
    $instance = $manager->createInstance($id, $settings['details'][$type][$id]);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): string {
    return 'storage';
  }

  /**
   * {@inheritdoc}
   */
  public function getSubDir(): string {
    return 'Plugin/Update/Storage';
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginInterface(): string {
    return PluginStorageInterface::class;
  }

  /**
   * {@inheritdoc}
   */
  public function getSelect(): array {
    return ['' => t('None')] + parent::getSelect();
  }

  /**
   * {@inheritdoc}
   */
  public function executableInstance(array $settings): PluginStorageInterface {
    if (empty($settings['current']['storage'])) {
      throw new \RuntimeException('Storage plugin is required');
    }

    /** @var PluginStorageInterface $pluginStorage */
    $pluginStorage = $this->instance('storage', $settings);

    /** @var \Drupal\drd\Update\PluginBuildInterface $build */
    $build = $this->instance('build', $settings);
    /** @var \Drupal\drd\Update\PluginProcessInterface $process */
    $process = $this->instance('process', $settings);
    /** @var \Drupal\drd\Update\PluginTestInterface $test */
    $test = $this->instance('test', $settings);
    /** @var \Drupal\drd\Update\PluginDeployInterface $deploy */
    $deploy = $this->instance('deploy', $settings);
    /** @var \Drupal\drd\Update\PluginFinishInterface $finish */
    $finish = $this->instance('finish', $settings);
    $pluginStorage->stepPlugins($build, $process, $test, $deploy, $finish);
    return $pluginStorage;
  }

  /**
   * {@inheritdoc}
   */
  public function buildGlobalForm(array &$form, FormStateInterface $form_state, array $settings): void {
    $settings = NestedArray::mergeDeep([
      'current' => [
        'storage' => '',
        'build' => 'direct',
        'process' => 'noprocess',
        'test' => 'notest',
        'deploy' => 'nodeploy',
        'finish' => 'nofinish',
      ],
      'details' => [
        'storage' => [],
        'build' => [],
        'process' => [],
        'test' => [],
        'deploy' => [],
        'finish' => [],
      ],
    ], $settings);
    $meta = $this->meta();

    $form['drd_update_method'] = [
      '#type' => 'fieldset',
      '#title' => t('Update method'),
    ];
    foreach ($meta as $type => $details) {
      /** @var ManagerInterface $manager */
      $manager = $details['manager'];
      $form['drd_update_method'][$type] = [
        '#type' => 'fieldset',
        '#title' => $details['label'],
        '#tree' => TRUE,
      ];
      $form['drd_update_method'][$type]['drd_upd_type_' . $type] = [
        '#type' => 'select',
        '#options' => $manager->getSelect(),
        '#default_value' => $settings['current'][$type],
      ];
      if ($type !== 'storage') {
        $form['drd_update_method'][$type]['#states'] = [
          'invisible' => ['select#edit-storage-drd-upd-type-storage' => ['value' => '']],
        ];
      }
      foreach ($manager->getSelect() as $id => $label) {
        if (empty($id)) {
          continue;
        }
        /** @var PluginInterface $upd */
        /* @noinspection PhpUnhandledExceptionInspection */
        $upd = $manager->createInstance($id);
        $upd->setConfiguration($settings['details'][$type][$id] ?? []);

        $condition = ['select#edit-' . $type . '-drd-upd-type-' . $type => ['value' => $id]];
        $upd->setConfigFormContext('drd_update_method', $condition);
        $form['drd_update_method'][$type][$id] = [
          '#type' => 'container',
          '#states' => [
            'visible' => $condition,
          ],
        ];
        $form['drd_update_method'][$type][$id] += $upd->buildConfigurationForm($form, $form_state);
        $this->plugins[$type][$id] = $upd;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateGlobalForm(array &$form, FormStateInterface $form_state): void {
    $current = [
      'storage' => $form_state->getValue(['storage', 'drd_upd_type_storage']),
      'build' => $form_state->getValue(['build', 'drd_upd_type_build']),
      'process' => $form_state->getValue(['process', 'drd_upd_type_process']),
      'test' => $form_state->getValue(['test', 'drd_upd_type_test']),
      'deploy' => $form_state->getValue(['deploy', 'drd_upd_type_deploy']),
      'finish' => $form_state->getValue(['finish', 'drd_upd_type_finish']),
    ];
    if (!empty($current['storage'])) {
      foreach ($this->plugins as $type => $plugins) {
        foreach ($plugins as $id => $plugin) {
          /** @var PluginInterface $plugin */
          if ($id === $current[$type]) {
            $plugin->validateConfigurationForm($form, $form_state);
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function globalFormValues(array $form, FormStateInterface $form_state): array {
    $settings = [
      'current' => [
        'storage' => $form_state->getValue(['storage', 'drd_upd_type_storage']),
        'build' => $form_state->getValue(['build', 'drd_upd_type_build']),
        'process' => $form_state->getValue(['process', 'drd_upd_type_process']),
        'test' => $form_state->getValue(['test', 'drd_upd_type_test']),
        'deploy' => $form_state->getValue(['deploy', 'drd_upd_type_deploy']),
        'finish' => $form_state->getValue(['finish', 'drd_upd_type_finish']),
      ],
      'details' => [],
    ];
    foreach ($this->plugins as $type => $plugins) {
      foreach ($plugins as $id => $plugin) {
        /** @var PluginInterface $plugin */
        $plugin->submitConfigurationForm($form, $form_state);
        $settings['details'][$type][$id] = $plugin->getConfiguration();
      }
    }
    return $settings;
  }

}
