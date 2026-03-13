<?php

namespace Drupal\drd_agent\Plugin\DrdPiAuth;

use Drupal\Component\Plugin\PluginBase;

/**
 * The base class for DRDPi autentication.
 */
abstract class DrdPiAuthBase extends PluginBase implements DrdPiAuthInterface {

  /**
   * Get the required properties for comparison.
   *
   * @return array
   *   The required properties.
   */
  abstract protected function getRequired(): array;

  /**
   * Return the local data for comparison.
   *
   * @return array
   *   The local data.
   */
  abstract protected function getLocal(): array;

  /**
   * {@inheritdoc}
   */
  public function validate(array $input): void {
    $local = $this->getLocal();
    $required = $this->getRequired();
    foreach ($required as $item) {
      if (!isset($local[$item])) {
        throw new \RuntimeException('Unsupported method.');
      }
      if ($local[$item] !== $input['secrets'][$item]) {
        throw new \RuntimeException('Invalid secret.');
      }
    }
  }

}
