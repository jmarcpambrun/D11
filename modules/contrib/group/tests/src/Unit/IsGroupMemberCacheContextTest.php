<?php

namespace Drupal\Tests\group\Unit;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\group\Cache\Context\IsGroupMemberCacheContext;
use Drupal\group\Entity\GroupInterface;
use Prophecy\Argument;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tests the user.is_group_member:%group_id cache context.
 *
 * @coversDefaultClass \Drupal\group\Cache\Context\IsGroupMemberCacheContext
 * @group group
 */
class IsGroupMemberCacheContextTest extends UnitTestCase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * A dummy group to use in other prophecies.
   *
   * @var \Drupal\group\Entity\GroupInterface
   */
  protected $group;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $account_proxy = $this->prophesize(AccountProxyInterface::class);
    $account_proxy->id()->willReturn(1337);
    $this->currentUser = $account_proxy->reveal();

    $group = $this->prophesize(GroupInterface::class);
    $group->id()->willReturn(1);
    $this->group = $group->reveal();
  }

  /**
   * Tests getting the context value from a non-calculated cache context.
   *
   * @covers ::getContext
   */
  public function testGetContextWithoutId() {
    $cache_context = new IsGroupMemberCacheContext(
      $this->currentUser,
      $this->createEntityTypeManager()->reveal(),
    );

    $this->setUpGroupMembership(FALSE);

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('No group ID provided for user.is_group_member cache context.');
    $cache_context->getContext();
  }

  /**
   * Tests getting the context value while specifying a non-existent group.
   *
   * @covers ::getContext
   */
  public function testGetContextWithInvalidGroupId() {
    $cache_context = new IsGroupMemberCacheContext(
      $this->currentUser,
      $this->createEntityTypeManager()->reveal(),
    );

    $this->setUpGroupMembership(FALSE);

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('Incorrect group ID provided for user.is_group_member cache context.');
    $cache_context->getContext(2);
  }

  /**
   * Tests getting the context value when the user is a member.
   *
   * @covers ::getContext
   */
  public function testGetContextMember() {
    $cache_context = new IsGroupMemberCacheContext(
      $this->currentUser,
      $this->createEntityTypeManager()->reveal(),
    );
    $this->setUpGroupMembership(TRUE);
    $this->assertSame('1', $cache_context->getContext(1));
  }

  /**
   * Tests getting the context value when the user is not a member.
   *
   * @covers ::getContext
   */
  public function testGetContextNotMember() {
    $cache_context = new IsGroupMemberCacheContext(
      $this->currentUser,
      $this->createEntityTypeManager()->reveal(),
    );
    $this->setUpGroupMembership(FALSE);
    $this->assertSame('0', $cache_context->getContext(1));
  }

  /**
   * Tests getting the cacheable metadata from a non-calculated cache context.
   *
   * @covers ::getCacheableMetadata
   */
  public function testGetCacheableMetadataWithoutId() {
    $cache_context = new IsGroupMemberCacheContext(
      $this->currentUser,
      $this->createEntityTypeManager()->reveal(),
    );

    $this->setUpGroupMembership(FALSE);

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('No group ID provided for user.is_group_member cache context.');
    $cache_context->getCacheableMetadata();
  }

  /**
   * Tests getting the cacheable metadata for a valid cache context.
   *
   * @covers ::getCacheableMetadata
   */
  public function testGetCacheableMetadata() {
    $cache_context = new IsGroupMemberCacheContext(
      $this->currentUser,
      $this->createEntityTypeManager()->reveal(),
    );

    $this->setUpGroupMembership(TRUE);

    $expected = (new CacheableMetadata())->addCacheTags(['group_relationship_list:plugin:group_membership:entity:1337']);
    $this->assertEquals($expected, $cache_context->getCacheableMetadata(1));
  }

  /**
   * Creates an EntityTypeManagerInterface prophecy.
   *
   * @return \Prophecy\Prophecy\ObjectProphecy
   *   The mock entity type manager.
   */
  protected function createEntityTypeManager() {
    $prophecy = $this->prophesize(EntityTypeManagerInterface::class);

    $storage = $this->prophesize(ContentEntityStorageInterface::class);
    $storage->load(Argument::any())->willReturn(NULL);
    $storage->load(1)->willReturn($this->group);
    $prophecy->getStorage('group')->willReturn($storage->reveal());

    return $prophecy;
  }

  /**
   * Sets up the GroupMembership so the static methods return what we want.
   *
   * @param bool $is_member
   *   Whether this will find the member or not.
   */
  protected function setUpGroupMembership($is_member): void {
    $cache_backend = $this->prophesize(CacheBackendInterface::class);
    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);

    $container = $this->prophesize(ContainerInterface::class);
    $container->get('cache.group_memberships_chained')->willReturn($cache_backend->reveal());
    $container->get('entity_type.manager')->willReturn($entity_type_manager->reveal());
    \Drupal::setContainer($container->reveal());

    // Pretend member ID is 1986.
    $cid = 'group_memberships:entity_id[1337]:roles[any-roles]';
    $cache_data = (object) ['data' => $is_member ? [1 => 1986] : []];
    $cache_backend->get($cid)->willReturn($cache_data);

    if ($is_member) {
      $storage = $this->prophesize(ContentEntityStorageInterface::class);
      $storage->load(1986)->willReturn(TRUE);
      $entity_type_manager->getStorage('group_relationship')->willReturn($storage->reveal());
    }
  }

}
