<?php

namespace Drupal\Tests\tour\Functional\Rest;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Tests\rest\Functional\AnonResourceTestTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests tour integration with restAPI, anon test.
 */
#[Group('tour')]
#[RunTestsInSeparateProcesses]
class TourJsonAnonTest extends TourResourceTestBase {

  use AnonResourceTestTrait;

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
  protected $defaultTheme = 'stark';

}
