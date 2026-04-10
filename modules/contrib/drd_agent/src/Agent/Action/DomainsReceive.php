<?php

namespace Drupal\drd_agent\Agent\Action;

use Drupal\Core\Logger\RfcLogLevel;

/**
 * Provides a 'DomainsReceive' code.
 */
class DomainsReceive extends Base {

  /**
   * {@inheritdoc}
   */
  public function execute(): array {
    $domains = [];

    foreach ($this->readSites() as $uri => $shortname) {
      $file = DRUPAL_ROOT . '/sites/' . $shortname . '/settings.php';
      if (!file_exists($file)) {
        continue;
      }
      if (isset($domains[$shortname])) {
        $domains[$shortname]['aliase'][] = $uri;
      }
      else {
        $domains[$shortname] = [
          'uri' => $uri,
          'aliase' => [],
        ];
      }
    }

    return $domains;
  }

  /**
   * Determines all available sites/domains in the current Drupal installation.
   *
   * @return array
   *   An array with key/value pairs where key is the domain name and value the
   *   shortname of a directory in DRUPAL_ROOT/sites/ for where to find the
   *   settings.php file for that domain.
   */
  private function readSites(): array {
    $sites = [];
    if (file_exists(DRUPAL_ROOT . '/sites/sites.php')) {
      try {
        include DRUPAL_ROOT . '/sites/sites.php';
      }
      catch (\Exception) {
        // Ignore.
      }
    }
    // The $sites variable could be non-empty, if the included file above
    // defined an array with site values.
    // @phpstan-ignore-next-line
    if (empty($sites)) {
      foreach (scandir(DRUPAL_ROOT . '/sites') as $shortname) {
        if (is_dir(DRUPAL_ROOT . '/sites/' . $shortname) &&
          !in_array($shortname, ['.', '..', 'all'])) {
          $file = DRUPAL_ROOT . '/sites/' . $shortname . '/settings.php';
          if (file_exists($file)) {
            [$base_url] = $this->readSettings($shortname, $file);
            if (empty($base_url)) {
              $this->watchdog('Reading Sites - Failed as url is empty: @shortname', [
                '@shortname' => $shortname,
              ], RfcLogLevel::ERROR);
              continue;
            }
            $pos = strpos($base_url, '://');
            if ($pos > 0) {
              $base_url = substr($base_url, $pos + 3);
            }
            $sites[$base_url] = $shortname;
          }
        }
      }
    }
    if (empty($sites)) {
      $base_url = $GLOBALS['base_url'];
      $sites[$base_url] = 'default';
    }
    $this->watchdog('Reading Sites - Found @n entries: <pre>@list</pre>', [
      '@n' => count($sites),
      '@list' => print_r($sites, TRUE),
    ]);
    return $sites;
  }

  /**
   * Safely read the settings.php file and return the relevant variables.
   *
   * @param string $shortname
   *   Name of the subdirectory in Drupal's site directory.
   * @param string $file
   *   Full path and filename to the settings.php which would be read.
   *
   * @return array
   *   An array containing the base url and database settings.
   */
  private function readSettings(string $shortname, string $file): array {
    // The following 2 variables may be required due to Drupal's
    // default.settings.php since version 8.2.x.
    // @see also https://www.drupal.org/node/2911759
    $app_root = $this->container->getParameter('app.root');
    $site_path = 'sites/' . $shortname;
    $class_loader = new DummyClassLoader();

    $base_url = '';
    $databases = [];
    try {
      $php = php_strip_whitespace($file);
      $php = str_replace(['<?php', '<?', '?>', 'ini_set', '@@ini_set', '__DIR__'], [
        '',
        '',
        '',
        '@ini_set',
        '@ini_set',
        "'" . str_replace('/settings.php', '', $file) . "'",
      ], $php);
      file_put_contents('temporary://drd-test.php', $php);
      //phpcs:disable
      eval($php);
    }
    catch (\Exception $e) {
      // Ignore it.
      $this->watchdog('Read Settings - Exception occured:<pre>@exception</pre>', [
        '@exception' => print_r($e, TRUE),
      ], RfcLogLevel::ERROR);
      return ['', ''];
    }
    // The $base_url variable could be non-empty, if the file content above
    // defined the base url.
    // @phpstan-ignore-next-line
    if (empty($base_url)) {
      if ($shortname === 'default') {
        $base_url = $GLOBALS['base_url'];
      }
      else {
        $base_url = $shortname;
      }
    }
    return [$base_url, $databases];
  }

}

/**
 * The dummy class loader.
 *
 * @package Drupal\drd_agent\Agent\Action
 */
class DummyClassLoader {

  /**
   * Adds the Psr4.
   */
  public function addPsr4(mixed $s1, mixed $s2): void  {}

}
