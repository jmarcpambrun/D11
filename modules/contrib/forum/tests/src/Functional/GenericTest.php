<?php

namespace Drupal\Tests\forum\Functional;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Group;

use Drupal\Tests\system\Functional\Module\GenericModuleTestBase;

/**
 * Generic module test for forum.
 *
 * @group forum
 */
#[Group('forum')]
#[RunTestsInSeparateProcesses]
class GenericTest extends GenericModuleTestBase {

  /**
   * {@inheritdoc}
   */
  protected function preUninstallSteps(): void {
    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $terms = $storage->loadMultiple();
    $storage->delete($terms);
  }

}
