<?php

namespace Drupal\drd\Plugin\Action;

use Drupal\drd\Entity\BaseInterface as RemoteEntityInterface;
use Drupal\drd\Entity\DomainInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Provides a 'Info' action.
 *
 * @Action(
 *  id = "drd_action_info",
 *  label = @Translation("Info"),
 *  eca_version_introduced = "4.1.0",
 *  type = "drd_domain",
 * )
 */
class Info extends BaseEntityRemote {

  /**
   * {@inheritdoc}
   */
  public function executeAction(?RemoteEntityInterface $entity = NULL): bool|array|string {
    if (!($entity instanceof DomainInterface)) {
      return FALSE;
    }
    $response = parent::executeAction($entity);
    if (is_array($response)) {
      if (!empty($response['settings']['container_yamls'])) {
        // Change container_yamls to relative paths.
        $fs = new Filesystem();
        $container_yamls = [];
        foreach ($response['settings']['container_yamls'] as $container_yaml) {
          if ($fs->isAbsolutePath($container_yaml)) {
            $container_yamls[] = trim($fs->makePathRelative($container_yaml, $response['root']), '/');
          }
        }
        $response['settings']['container_yamls'] = $container_yamls;
      }

      if (!empty($response['monitoring'])) {
        $this->analyzeMonitoring($response['monitoring'], $response['requirements']);
      }

      /* @noinspection PhpUnhandledExceptionInspection */
      $entity
        ->setName($response['name'])
        ->cacheGlobals($response['globals'])
        ->cacheRequirements($response['requirements'])
        ->cacheSettings($response['settings'])
        ->cacheVariables($response['variables'])
        ->cacheReview($response['review'])
        ->cacheMonitoring($response['monitoring'])
        ->save();
      return $response;
    }
    return FALSE;
  }

  /**
   * Go through monitoring and add record to requirements.
   *
   * @param array $values
   *   The monitoring values.
   * @param array $requirements
   *   The requirements to which we add a new record.
   */
  private function analyzeMonitoring(array $values, array &$requirements): void {
    // Load the install file, that also loads install.inc from Drupal core.
    $this->moduleHandler->loadInclude('drd', 'install');
    $severity = REQUIREMENT_OK;
    foreach ($values as $item) {
      switch ($item['status']) {
        case 'CRITICAL':
          $severity = REQUIREMENT_ERROR;
          break;

        case 'WARNING':
          $severity = max(REQUIREMENT_WARNING, $severity);
          break;

      }
    }

    $description = match ($severity) {
      REQUIREMENT_WARNING => $this->t('The monitoring recognized seomthing that you should pay attention for.'),
      REQUIREMENT_ERROR => $this->t('The monitoring identified warnings for you.'),
      default => $this->t('The monitoring does not report any issues.'),
    };

    // @todo Add a link to the description.
    $requirements['drd.monitoring'] = [
      'title' => $this->t('Monitoring'),
      'description' => $description,
      'severity' => $severity,
    ];
  }

}
