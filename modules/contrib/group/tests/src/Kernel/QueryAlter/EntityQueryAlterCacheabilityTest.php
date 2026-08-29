<?php

namespace Drupal\Tests\group\Kernel\QueryAlter;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Core\Render\RenderContext;
use Drupal\Tests\group\Kernel\GroupKernelTestBase;
use Drupal\Tests\group\Traits\NodeTypeCreationTrait;
use Drupal\group\Entity\Storage\GroupRelationshipTypeStorageInterface;

/**
 * Tests grouped entities query access cacheability.
 *
 * @coversDefaultClass \Drupal\group\QueryAccess\EntityQueryAlter
 */
#[Group('group')]
#[RunTestsInSeparateProcesses]
class EntityQueryAlterCacheabilityTest extends GroupKernelTestBase {

  use NodeTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node'];

  /**
   * The group type to use in testing.
   *
   * @var \Drupal\group\Entity\GroupTypeInterface
   */
  protected $groupType;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('node');
    $this->createNodeType(['type' => 'page']);

    $this->groupType = $this->createGroupType();
  }

  /**
   * Tests that cacheable metadata is only bubbled when there is any.
   */
  public function testCacheableMetadataLeaks() {
    $renderer = $this->container->get('renderer');
    $storage = $this->entityTypeManager->getStorage('node');

    // Create an ungrouped node. This should not trigger the query access and
    // therefore not leak cacheable metadata.
    $this->createNode(['type' => 'page']);

    $render_context = new RenderContext();
    $renderer->executeInRenderContext($render_context, static function () use ($storage) {
      $storage->getQuery()->accessCheck()->execute();
    });
    $this->assertTrue($render_context->isEmpty(), 'Empty cacheability was not bubbled.');

    // Install the test module so we have an access plugin for nodes.
    $this->enableModules(['group_test_plugin']);
    $this->installConfig('group_test_plugin');

    // Refresh the managers so they use the new namespaces.
    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->pluginManager = $this->container->get('group_relation_type.manager');

    // Install the plugin and add a node to a group so query access kicks in and
    // cacheable metadata is added to the query.
    $relationship_type_storage = $this->entityTypeManager->getStorage('group_relationship_type');
    assert($relationship_type_storage instanceof GroupRelationshipTypeStorageInterface);
    $relationship_type_storage->save($relationship_type_storage->createFromPlugin($this->groupType, 'node_relation:page'));
    $group = $this->createGroup(['type' => $this->groupType->id()]);
    $group->addRelationship($this->createNode(['type' => 'page']), 'node_relation:page');

    $render_context = new RenderContext();
    $renderer->executeInRenderContext($render_context, static function () use ($storage) {
      $storage->getQuery()->accessCheck()->execute();
    });
    $this->assertFalse($render_context->isEmpty(), 'Cacheability was bubbled');
    $this->assertCount(1, $render_context);
    $this->assertEqualsCanonicalizing([
      'group_relationship_list:plugin:node_relation:article',
      'group_relationship_list:plugin:node_relation:page',
    ], $render_context[0]->getCacheTags());
  }

  /**
   * Creates a node.
   *
   * @param array $values
   *   (optional) The values used to create the entity.
   *
   * @return \Drupal\node\Entity\Node
   *   The created node entity.
   */
  protected function createNode(array $values = []) {
    $storage = $this->entityTypeManager->getStorage('node');
    $node = $storage->create($values + [
      'title' => $this->randomString(),
    ]);
    $node->enforceIsNew();
    $storage->save($node);
    return $node;
  }

}
