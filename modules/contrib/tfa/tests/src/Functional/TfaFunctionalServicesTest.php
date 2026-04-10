<?php

declare(strict_types=1);

namespace Drupal\Tests\tfa\Functional;

use Drupal\Core\Cache\MemoryBackend;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the TFA Memory Cache service.
 */
final class TfaFunctionalServicesTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';


  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'tfa',
    'encrypt',
    'key',
  ];

  /**
   * Tests 'cache.tfa_memcache' is backed by memory.
   *
   * @group tfa
   */
  public function testTfaMemoryCache():void {
    $service = \Drupal::service('cache.tfa_memcache');
    $this->assertInstanceOf(MemoryBackend::class, $service, 'cache.tfa_memcache must be backed by the memory backend.');
  }

}
