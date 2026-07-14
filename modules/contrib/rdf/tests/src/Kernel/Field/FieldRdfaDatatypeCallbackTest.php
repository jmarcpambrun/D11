<?php

namespace Drupal\Tests\rdf\Kernel\Field;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\rdf\RdfMappingHelper;

/**
 * Tests the RDFa output of a text field formatter with a datatype callback.
 */
#[Group('rdf')]
#[RunTestsInSeparateProcesses]
class FieldRdfaDatatypeCallbackTest extends FieldRdfaTestBase {

  /**
   * {@inheritdoc}
   */
  protected string $fieldType = 'text';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['text', 'filter', 'rdf_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createTestField();

    $this->installConfig(['filter']);

    // Add the mapping.
    $mapping = \Drupal::service(RdfMappingHelper::class)->getMapping('entity_test', 'entity_test');
    $mapping->setFieldMapping($this->fieldName, [
      'properties' => ['schema:interactionCount'],
      'datatype_callback' => [
        'callable' => 'Drupal\rdf_test\TestDataConverter::convertFoo',
      ],
    ])->save();

    // Set up test values.
    $this->testValue = $this->randomMachineName();
    $this->entity = EntityTest::create();
    $this->entity->{$this->fieldName}->value = $this->testValue;
    $this->entity->save();

    $this->uri = $this->getAbsoluteUri($this->entity);
  }

  /**
   * Tests the default formatter.
   */
  public function testDefaultFormatter(): void {
    // Expected value is the output of the datatype callback, not the raw value.
    $this->assertFormatterRdfa(['type' => 'text_default'], 'http://schema.org/interactionCount', ['value' => 'foo' . $this->testValue]);
  }

}
