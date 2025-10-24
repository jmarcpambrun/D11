<?php
/**
 * Created by PhpStorm.
 * User: steve
 * Date: 20/07/18
 * Time: 14:23
 */

namespace Drupal\universal_file_utils\Event;

class UniversalFileDownloadEvent extends UniversalFileBaseEvent {

  const NAME = 'universal_file_utils.download';

  /**
   * Must be private in this case, don't want derived classes messing with it.
   *
   * @var string[] | int
   */
  private $headers = [];

  /**
   * @var bool
   */
  private $denyDownload = FALSE;

  /**
   * @return string[] | int
   */
  public function getHeaders() {
    return $this->downloadDenied() ? -1 : $this->headers;
  }

  /**
   * Add these headers
   *
   * @param string[] $headers
   */
  public function setHeaders(array $headers) {
    $this->headers = array_merge($this->headers, $headers);
  }

  /**
   * Prevent download of this file.
   */
  public function denyDownload() {
      $this->denyDownload = TRUE;
  }

    /**
     * @return bool
     */
  public function downloadDenied(): bool {
      return $this->denyDownload;
  }

}
