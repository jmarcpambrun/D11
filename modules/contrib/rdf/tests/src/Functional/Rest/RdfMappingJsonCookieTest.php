<?php

namespace Drupal\Tests\rdf\Functional\Rest;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Tests\rest\Functional\CookieResourceTestTrait;

/**
 * Tests JSON Cookie for RDF mappings.
 *
 * @group rest
 */
#[Group('rest')]
#[RunTestsInSeparateProcesses]
class RdfMappingJsonCookieTest extends RdfMappingResourceTestBase {

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
