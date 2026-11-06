<?php

namespace Drupal\Tests\tour\Functional\Rest;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Tests\rest\Functional\AnonResourceTestTrait;
use Drupal\Tests\rest\Functional\EntityResource\XmlEntityNormalizationQuirksTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests tour integration with restAPI, XML anon test.
 */
#[Group('tour')]
#[RunTestsInSeparateProcesses]
class TourXmlAnonTest extends TourResourceTestBase {

  use AnonResourceTestTrait;
  use XmlEntityNormalizationQuirksTrait;

  /**
   * {@inheritdoc}
   */
  protected static $format = 'xml';

  /**
   * {@inheritdoc}
   */
  protected static $mimeType = 'text/xml; charset=utf-8';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

}
