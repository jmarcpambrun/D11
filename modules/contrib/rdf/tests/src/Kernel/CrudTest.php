<?php

namespace Drupal\Tests\rdf\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\KernelTests\KernelTestBase;
use Drupal\rdf\RdfMappingHelper;

/**
 * Tests the RDF mapping CRUD functions.
 */
#[Group('rdf')]
#[RunTestsInSeparateProcesses]
class CrudTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['entity_test', 'field', 'rdf', 'system', 'text', 'user'];

  /**
   * The prefix.
   *
   * @var string
   */
  protected string $prefix;

  /**
   * Entity type.
   *
   * @var string
   */
  protected string $entityType;

  /**
   * Bundle name.
   *
   * @var string
   */
  protected string $bundle;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('entity_test');
    $this->prefix = 'rdf.mapping';
    $this->entityType = $this->bundle = 'entity_test';
  }

  /**
   * Tests creation of RDF mapping.
   */
  public function testMappingCreation(): void {
    $mapping_config_name = "{$this->prefix}.{$this->entityType}.{$this->bundle}";

    // Save bundle mapping config.
    \Drupal::service(RdfMappingHelper::class)->getMapping($this->entityType, $this->bundle)->save();
    // Test that config file was saved.
    $mapping_config = \Drupal::configFactory()->listAll('rdf.mapping.');
    $this->assertContains($mapping_config_name, $mapping_config, 'Rdf mapping config saved.');
  }

  /**
   * Tests the handling of bundle mappings.
   */
  public function testBundleMapping(): void {
    // Test that the bundle mapping can be saved.
    $types = ['sioc:Post', 'foaf:Document'];
    \Drupal::service(RdfMappingHelper::class)->getMapping($this->entityType, $this->bundle)
      ->setBundleMapping(['types' => $types])
      ->save();
    $bundle_mapping = \Drupal::service(RdfMappingHelper::class)->getMapping($this->entityType, $this->bundle)
      ->getBundleMapping();
    $this->assertEquals($types, $bundle_mapping['types'], 'Bundle mapping saved.');

    // Test that the bundle mapping can be edited.
    $types = ['schema:BlogPosting'];
    \Drupal::service(RdfMappingHelper::class)->getMapping($this->entityType, $this->bundle)
      ->setBundleMapping(['types' => $types])
      ->save();
    $bundle_mapping = \Drupal::service(RdfMappingHelper::class)->getMapping($this->entityType, $this->bundle)
      ->getBundleMapping();
    $this->assertEquals($types, $bundle_mapping['types'], 'Bundle mapping updated.');
  }

  /**
   * Tests the handling of field mappings.
   */
  public function testFieldMapping(): void {
    $field_name = 'created';

    // Test that the field mapping can be saved.
    $mapping = [
      'properties' => ['dc:created'],
      'datatype' => 'xsd:dateTime',
      'datatype_callback' => ['callable' => 'Drupal\rdf\CommonDataConverter::dateIso8601Value'],
    ];
    \Drupal::service(RdfMappingHelper::class)->getMapping($this->entityType, $this->bundle)
      ->setFieldMapping($field_name, $mapping)
      ->save();
    $field_mapping = \Drupal::service(RdfMappingHelper::class)->getMapping($this->entityType, $this->bundle)
      ->getFieldMapping($field_name);
    $this->assertEquals($mapping, $field_mapping, 'Field mapping saved.');

    // Test that the field mapping can be edited.
    $mapping = [
      'properties' => ['dc:date'],
      'datatype' => 'foo:bar',
      'datatype_callback' => ['callable' => 'Drupal\rdf\CommonDataConverter::dateIso8601Value'],
    ];
    \Drupal::service(RdfMappingHelper::class)->getMapping($this->entityType, $this->bundle)
      ->setFieldMapping($field_name, $mapping)
      ->save();
    $field_mapping = \Drupal::service(RdfMappingHelper::class)->getMapping($this->entityType, $this->bundle)
      ->getFieldMapping($field_name);
    $this->assertEquals($mapping, $field_mapping, 'Field mapping updated.');
  }

}
