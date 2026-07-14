<?php

namespace Drupal\Tests\rdf\Functional\Rest;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Tests\rest\Functional\AnonResourceTestTrait;
use Drupal\Tests\rest\Functional\EntityResource\XmlEntityNormalizationQuirksTrait;

/**
 * Tests XML Anon for RDF mappings.
 *
 * @group rest
 */
#[Group('rest')]
#[RunTestsInSeparateProcesses]
class RdfMappingXmlAnonTest extends RdfMappingResourceTestBase {

  use AnonResourceTestTrait;
  use XmlEntityNormalizationQuirksTrait;

  /**
   * {@inheritdoc}
   */
  protected static $format = 'xml';

  /**
   * {@inheritdoc}
   */
  protected static $mimeType = 'text/xml; charset=UTF-8';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

}
