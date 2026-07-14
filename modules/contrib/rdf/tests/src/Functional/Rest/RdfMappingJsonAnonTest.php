<?php

namespace Drupal\Tests\rdf\Functional\Rest;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Tests\rest\Functional\AnonResourceTestTrait;

/**
 * Tests JSON Anon for RDF mappings.
 *
 * @group rest
 */
#[Group('rest')]
#[RunTestsInSeparateProcesses]
class RdfMappingJsonAnonTest extends RdfMappingResourceTestBase {

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
