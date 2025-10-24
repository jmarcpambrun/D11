<?php
/**
 * Created by PhpStorm.
 * User: steve
 * Date: 20/07/18
 * Time: 14:23
 */

namespace Drupal\universal_file_utils\Event;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;

class UniversalFileAccessEvent extends UniversalFileBaseEvent {

  const NAME = 'universal_file_utils.access';

  /**
   * Set up a access results.
   *
   * @return mixed[]
   */
  public function getExtras() {
    return [
      'accessResults' => [],
    ] + parent::getExtras();
  }

  /**
   * @param string $source
   */
  public function allowed($source) {
    $this->setAccessResult($source, AccessResult::allowed());
  }

  /**
   * @param string $source
   */
  public function neutral($source) {
    $this->setAccessResult($source, AccessResult::neutral());
  }

  /**
   * @param string $source
   */
  public function forbidden($source) {
    $this->setAccessResult($source, AccessResult::forbidden());
  }

  /**
   * @param string $source
   *
   * @param AccessResultInterface $accessResult
   */
  public function setAccessResult($source, AccessResultInterface $accessResult) {
    $this->extras['accessResults'][$source] = $accessResult;
  }

  /**
   * Process any results we got from this event to see whether we are
   * allowing, forbidding, or we just don't care.
   *
   * @return AccessResultInterface
   */
  public function getAccessResult() {
    // Default the result to neutral...
    $result = AccessResult::neutral();

    /** @var AccessResultInterface $accessResult */
    foreach ($this->get('accessResults', []) as $accessResult) {
      // We just ignore neutrals...
      if (!$accessResult->isNeutral()) {
        if ($accessResult->isForbidden()) {
          // Just one forbidden makes this forbidden. Exit loop.
          $result = $accessResult;
          break;
        }
      }
    }
    return $result;
  }

  /**
   * @return string
   */
  public function getOperation() {
    return $this->get('operation');
  }

  /**
   * @return AccountInterface
   */
  public function getAccount() {
    return $this->get('account');
  }

}
