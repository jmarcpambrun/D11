<?php

namespace Drupal\Tests\tfa\Functional\UpdateTests;

/**
 * Test basic E2E operation of login block plugin conversion post_update hook.
 *
 * @group tfa
 *
 * @coversNothing
 */
class TfaUserLoginBlockRemovedTest extends UpdateTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    parent::setDatabaseDumpFiles();
    $this->databaseDumpFiles[] = __DIR__ . '/../../../fixtures/tfa_user_login_block.php';
  }

  /**
   * Validate login blocks no longer use the tfa_user_login plugin.
   */
  public function testLoginBlockPluginConverted(): void {
    $this->runUpdates();

    $result = \Drupal::entityQuery('block')
      ->condition('plugin', 'tfa_user_login_block')
      ->accessCheck(FALSE)
      ->execute();

    $this->assertEmpty($result);

    $result = \Drupal::entityQuery('block')
      ->condition('plugin', 'user_login_block')
      ->accessCheck(FALSE)
      ->execute();

    $this->assertCount(1, $result);

    $storage = \Drupal::entityTypeManager()->getStorage('block');
    /** @var ?\Drupal\block\BlockInterface $block */
    $block = $storage->load(reset($result));

    $this->assertNotNull($block);

    $this->assertSame('user_login_block', $block->get('plugin'));
    $settings = $block->get('settings');
    $this->assertIsArray($settings);
    $this->assertArrayHasKey('provider', $settings);
    $this->assertSame('user', $settings['provider']);
  }

}
