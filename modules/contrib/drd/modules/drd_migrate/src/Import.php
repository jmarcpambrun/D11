<?php

namespace Drupal\drd_migrate;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\drd\Entity\Domain;
use Drupal\drd\Entity\Host;

/**
 * Provides import services.
 *
 * @package Drupal\drd
 */
class Import {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Import constructor.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(AccountProxyInterface $current_user, EntityTypeManagerInterface $entity_type_manager) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Manage output to console depending on context.
   *
   * @param string $text
   *   The text to be output.
   * @param bool $error
   *   Indicate if this is an error message or not.
   */
  private function output(string $text, bool $error = FALSE): void {
    if ($error) {
      // @todo Replace with proper output function.
      // drush_set_error($text);
      print('Error: ' . $text);
    }
    else {
      print($text);
    }
  }

  /**
   * Execute the import command.
   *
   * @param string $filename
   *   The full path and filename which holds the inventory for import.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  public function execute(string $filename): void {
    if (!file_exists($filename)) {
      $this->output('Inventory file does not exist!', TRUE);
      return;
    }
    try {
      $inventory = Json::decode(file_get_contents($filename));
    }
    catch (\Exception) {
      $this->output('Inventory file can not be read!', TRUE);
      return;
    }
    /** @var \Drupal\Core\Session\AccountInterface $account */
    $account = $this->entityTypeManager->getStorage('user')->load(1);
    $this->currentUser->setAccount($account);
    $storage = $this->entityTypeManager->getStorage('drd_core');

    foreach ($inventory as $id => $coredomains) {
      $this->output('Import core ' . $id);
      /** @var \Drupal\drd\Entity\Core $core */
      $core = $storage->create([
        'name' => 'Migrate ' . $id,
      ]);
      foreach ($coredomains as $coredomain) {
        /* @noinspection HttpUrlsUsage */
        $url = $coredomain['ssl'] ? 'https://' : 'http://';
        $url .= $coredomain['url'];
        $this->output('  Import ' . $url);
        $domain = Domain::instanceFromUrl($core, $url, []);
        if ($domain->isNew()) {
          $domain->initValues($coredomain['url']);
        }
        else {
          $core = $domain->getCore();
        }
        if ($domain->pushOTT($coredomain['token'])) {
          if ($core->isNew()) {
            // Try to find the correct host or create a new one.
            $host = Host::findOrCreateByHost(parse_url($url, PHP_URL_HOST));
            $core->setHost($host);
            if (!$domain->initCore($core)) {
              continue;
            }
          }
          $domain->set('installed', 1);
          $domain->setCore($core);
          $domain->save();
          $core = $domain->getCore();
        }
      }
    }
  }

}
