<?php

namespace Drupal\Tests\rdf\Kernel\Field;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\rdf\RdfMappingHelper;

/**
 * Tests RDFa output by text field formatters.
 */
#[Group('rdf')]
#[RunTestsInSeparateProcesses]
class StringFieldRdfaTest extends FieldRdfaTestBase {

  /**
   * {@inheritdoc}
   */
  protected string $fieldType = 'string';

  /**
   * The 'value' property value for testing.
   *
   * @var string
   */
  protected string $testValue = 'test_text_value';

  /**
   * The 'summary' property value for testing.
   *
   * @var string
   */
  protected string $testSummary = 'test_summary_value';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createTestField();

    // Add the mapping.
    $mapping = \Drupal::service(RdfMappingHelper::class)->getMapping('entity_test', 'entity_test');
    $mapping->setFieldMapping($this->fieldName, [
      'properties' => ['schema:text'],
    ])->save();

    // Set up test entity.
    $this->entity = EntityTest::create();
    $this->entity->{$this->fieldName}->value = $this->testValue;
    $this->entity->{$this->fieldName}->summary = $this->testSummary;
  }

  /**
   * Tests string formatters.
   */
  public function testStringFormatters(): void {
    // Tests the string formatter.
    $this->assertFormatterRdfa(['type' => 'string'], 'http://schema.org/text', ['value' => $this->testValue]);
  }

}
