<?php

namespace Drupal\Tests\tour\Functional\Rest;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Tests\rest\Functional\CookieResourceTestTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests tour integration with restAPI, cookie test.
 */
#[Group('tour')]
#[RunTestsInSeparateProcesses]
class TourJsonCookieTest extends TourResourceTestBase {

  use CookieResourceTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $format = 'json';

  /**
   * {@inheritdoc}
   */
  protected static $mimeType = 'application/json';

  /**
   * {@inheritdoc}
   */
  protected static $auth = 'cookie';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

}
