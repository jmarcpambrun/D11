<?php

namespace Drupal\Tests\book\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test installation of Book module.
 */
#[Group('book')]
#[RunTestsInSeparateProcesses]
class BookInstallTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'system',
  ];

  /**
   * Tests Book install with pre-existing content type.
   *
   * Tests that Book module can be installed if content type with machine name
   * 'book' already exists.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  public function testBookInstallWithPreexistingContentType(): void {
    // Create a 'book' content type.
    NodeType::create([
      'type' => 'book',
      'name' => 'Book',
    ])->save();
    // Install the Book module. Using the module installer service ensures that
    // all the installation rituals, including default and optional
    // configuration import, are performed.
    $status = $this->container->get('module_installer')->install(['book']);
    $this->assertTrue($status, 'Book module installed successfully');
  }

}
