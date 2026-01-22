<?php

namespace Drupal\Tests\computed_field\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Render\Element;
use Drupal\dynamic_page_cache\PageCache\RequestPolicy\DefaultRequestPolicy as DynamicPageCacheDefaultRequestPolicy;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests output and caching of computed fields.
 *
 * @group computed_field
 */
class ComputedFieldDisplayKernelTest extends KernelTestBase implements ServiceModifierInterface {

  use UserCreationTrait;

  /**
   * The modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'dynamic_page_cache',
    'user',
    'field',
    'entity_test',
    'computed_field',
    'test_computed_field_output',
  ];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity display repository service.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // @todo Remove these and rework other parts of this test when
    // https://www.drupal.org/project/drupal/issues/3390193 is fixed.
    $service_definition = $container->getDefinition('dynamic_page_cache_request_policy');
    $service_definition->setClass(KernelTestDynamicPageCacheRequestPolicy::class);

    // Enable the 'x-drupal-cache-tags' response header.
    $container->setParameter('http.response.debug_cacheability_headers', TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    // We don't need to test bundles, so use an entity type without them.
    $this->installEntitySchema('entity_test');

    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->entityFieldManager = $this->container->get('entity_field.manager');
    $this->entityDisplayRepository = $this->container->get('entity_display.repository');
  }

  /**
   * Tests that computed fields are displayed on an entity.
   */
  public function testComputedFieldsOutput() {
    // Sanity check.
    $field_definitions = $this->entityFieldManager->getFieldDefinitions('entity_test', 'entity_test');
    $this->assertArrayHasKey('test_string', $field_definitions);
    $this->assertArrayHasKey('test_current_user', $field_definitions);
    $this->assertArrayHasKey('test_cache_tags', $field_definitions);
    $this->assertArrayHasKey('test_request_timestamp', $field_definitions);
    $this->assertArrayHasKey('test_empty', $field_definitions);

    // Create a current user with a role.
    $role_alpha = $this->createRole(['view test entity'], rid: 'alpha');
    $user_alpha = $this->createUser(name: 'Alpha user', values: ['roles' => [$role_alpha]]);
    $this->container->get('current_user')->setAccount($user_alpha);

    $entity_test_storage = $this->entityTypeManager->getStorage('entity_test');
    $view_builder = $this->entityTypeManager->getHandler('entity_test', 'view_builder');

    $alpha_entity = $entity_test_storage->create([]);
    $alpha_entity->save();

    $http_kernel = $this->container->get('http_kernel');
    $request = Request::create('/entity_test/' . $alpha_entity->id());
    $request->setSession(new Session(new MockArraySessionStorage()));
    $response = $http_kernel->handle($request);
    $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

    // The cache tag from the test_cache_tags plugin is present in the page's
    // cache tags.
    $this->assertStringContainsString('banana', $response->headers->get('x-drupal-cache-tags'));

    // Build the entity view render array, including the pre_render callback to
    // fill in the fields' render arrays.
    $build = $view_builder->view($alpha_entity);
    $build = $view_builder->build($build);

    $this->assertArrayHasKey('test_string', $build);
    $this->assertArrayNotHasKey('#lazy_builder', $build['test_string']);

    $this->assertArrayHasKey('test_current_user', $build);
    $this->assertEquals('computed_field.computed_field_builder:viewField', $build['test_current_user']['#lazy_builder'][0]);

    $this->assertArrayHasKey('test_request_timestamp', $build);
    $this->assertEquals('computed_field.computed_field_builder:viewField', $build['test_request_timestamp']['#lazy_builder'][0]);

    // The field has no items.
    $this->assertArrayHasKey('test_empty', $build);
    $this->assertEmpty(Element::children($build['test_empty']));

    // Render the build array.
    $html = $this->render($build);

    $this->assertStringContainsString('cake!', $html);
    $this->assertStringContainsString('Alpha user', $html);
    $this->assertStringContainsString((string) \Drupal::time()->getRequestTime(), $html);
  }

  /**
   * Reloads the given entity from the storage and returns it.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to be reloaded.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The reloaded entity.
   */
  protected function reloadEntity(EntityInterface $entity) {
    $controller = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
    $controller->resetCache([$entity->id()]);
    return $controller->load($entity->id());
  }

}

/**
 * Replaces the dynamic_page_cache module's default request policy.
 */
class KernelTestDynamicPageCacheRequestPolicy extends DynamicPageCacheDefaultRequestPolicy {

  public function __construct() {
  }

}
