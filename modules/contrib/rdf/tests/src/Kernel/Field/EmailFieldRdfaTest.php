<?php

namespace Drupal\Tests\rdf\Kernel\Field;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\rdf\RdfMappingHelper;

/**
 * Tests RDFa output by email field formatters.
 */
#[Group('rdf')]
#[RunTestsInSeparateProcesses]
class EmailFieldRdfaTest extends FieldRdfaTestBase {

  /**
   * {@inheritdoc}
   */
  protected string $fieldType = 'email';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['text'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createTestField();

    // Add the mapping.
    $mapping = \Drupal::service(RdfMappingHelper::class)->getMapping('entity_test', 'entity_test');
    $mapping->setFieldMapping($this->fieldName, [
      'properties' => ['schema:email'],
    ])->save();

    // Set up test values.
    $this->testValue = 'test@example.com';
    $this->entity = EntityTest::create([]);
    $this->entity->{$this->fieldName}->value = $this->testValue;
  }

  /**
   * Tests all email formatters.
   */
  public function testAllFormatters(): void {
    // Test the plain formatter.
    $this->assertFormatterRdfa(['type' => 'string'], 'http://schema.org/email', ['value' => $this->testValue]);
    // Test the mailto formatter.
    $this->assertFormatterRdfa(['type' => 'email_mailto'], 'http://schema.org/email', ['value' => $this->testValue]);
  }

}
