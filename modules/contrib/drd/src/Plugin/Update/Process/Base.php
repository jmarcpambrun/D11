<?php

namespace Drupal\drd\Plugin\Update\Process;

use Drupal\Core\Form\FormStateInterface;
use Drupal\drd\Plugin\Update\UpdateBase;
use Drupal\drd\Update\PluginInterface;
use Drupal\drd\Update\PluginProcessInterface;
use Drupal\drd\Update\PluginStorageInterface;

/**
 * Abstract DRD Update plugin to implement general process functionality.
 */
abstract class Base extends UpdateBase implements PluginProcessInterface {

  /**
   * Indicates if the process succeeded.
   *
   * @var bool
   */
  protected bool $succeeded = FALSE;

  /**
   * Holds the original content of the sites file if present.
   *
   * @var bool|string
   */
  private mixed $originalSites = FALSE;

  /**
   * All domains that need processing.
   *
   * @var \Drupal\drd\Entity\DomainInterface[]
   */
  protected array $domains = [];

  /**
   * {@inheritdoc}
   */
  final public function hasSucceeded(): bool {
    return $this->succeeded;
  }

  /**
   * Determin if the plugin requires the site's database for processing.
   *
   * @return bool
   *   TRUE if the database was required.
   */
  protected function requiresDatabase(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $element = parent::buildConfigurationForm($form, $form_state);

    $element['pulldb'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Pull database(s) from production server'),
      '#default_value' => $this->requiresDatabase() ? TRUE : $this->configuration['pulldb'],
      '#disabled' => $this->requiresDatabase(),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['pulldb'] = !empty($this->getFormValue($form_state, 'pulldb'));
  }

  /**
   * {@inheritdoc}
   */
  public function process(PluginStorageInterface $storage): PluginProcessInterface {
    $this->domains = $storage->getCore()->getDomains();
    if ($this->configuration['pulldb']) {
      $sites_file = $storage->getDrupalDirectory() . DIRECTORY_SEPARATOR . 'sites/sites.php';
      if (file_exists($sites_file)) {
        $this->originalSites = file_get_contents($sites_file);
        unlink($sites_file);
      }
      foreach ($this->domains as $domain) {
        $result = $this->domainLocalCopy
          ->setDrupalDirectory($storage->getDrupalDirectory())
          ->setWorkingDirectory($storage->getWorkingDirectory())
          ->setDomain($domain)
          ->setup();
        /* @noinspection DisconnectedForeachInstructionInspection */
        $storage->log($this->domainLocalCopy->getLog());
        if (!$result) {
          throw new \RuntimeException('Can not pull database.');
        }
      }
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function cleanup(PluginStorageInterface $storage): PluginInterface {
    parent::cleanup($storage);
    $sites_file = $storage->getDrupalDirectory() . DIRECTORY_SEPARATOR . 'sites/sites.php';
    if ($this->originalSites) {
      file_put_contents($sites_file, $this->originalSites);
    }
    elseif (file_exists($sites_file)) {
      unlink($sites_file);
    }
    foreach ($this->domains as $domain) {
      $this->domainLocalCopy
        ->setDomain($domain)
        ->dropDatabases();
    }
    return $this;
  }

}
