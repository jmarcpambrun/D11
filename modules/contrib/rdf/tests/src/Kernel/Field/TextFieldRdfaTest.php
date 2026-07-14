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
class TextFieldRdfaTest extends FieldRdfaTestBase {

  /**
   * {@inheritdoc}
   */
  protected string $fieldType = 'text';

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
  protected static $modules = ['text', 'filter'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['filter']);

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
   * Tests all formatters.
   *
   * @todo Check for the summary mapping.
   */
  public function testAllFormatters(): void {
    $formatted_value = strip_tags($this->entity->{$this->fieldName}->processed);

    // Tests the default formatter.
    $this->assertFormatterRdfa(['type' => 'text_default'], 'http://schema.org/text', ['value' => $formatted_value]);
    // Tests the summary formatter.
    $this->assertFormatterRdfa(['type' => 'text_summary_or_trimmed'], 'http://schema.org/text', ['value' => $formatted_value]);
    // Tests the trimmed formatter.
    $this->assertFormatterRdfa(['type' => 'text_trimmed'], 'http://schema.org/text', ['value' => $formatted_value]);
  }

}
