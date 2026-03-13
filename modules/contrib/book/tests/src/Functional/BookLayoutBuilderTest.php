<?php

declare(strict_types=1);

namespace Drupal\Tests\book\Functional;

use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests interaction with layout builder.
 */
#[Group('book')]
#[RunTestsInSeparateProcesses]
class BookLayoutBuilderTest extends BookTestBase {

  use BookTestTrait;
  use ContentModerationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'book',
    'book_content_type',
    'field_ui',
    'node',
    'layout_builder',
    'content_moderation',
  ];

  /**
   * Tests layout builder content types are un-affected by book.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testBookWithLayoutBuilderInstalled(): void {
    $this->drupalCreateContentType(['type' => 'test_content_type']);
    LayoutBuilderEntityViewDisplay::load('node.test_content_type.default')
      ->enableLayoutBuilder()
      ->setOverridable()
      ->save();

    $workflow = $this->createEditorialWorkflow();
    $workflow->getTypePlugin()->addEntityTypeAndBundle('node', 'test_content_type');
    $workflow->save();

    $node = $this->createNode([
      'type' => 'test_content_type',
      'title' => 'The first node title',
      'moderation_state' => 'published',
    ]);

    $this->drupalLogin($this->drupalCreateUser([
      'configure any layout',
      'bypass node access',
      'create test_content_type content',
      'edit any test_content_type content',
      'use editorial transition create_new_draft',
      'use editorial transition publish',
      'create new books',
      'create book content',
      'edit any book content',
      'delete any book content',
      'add content to books',
      'reorder book pages',
      'administer book outlines',
      'view any unpublished content',
      'view book revisions',
    ]));

    $this->assertTrue($node->isPublished());

    // Confirm that we can save a draft from layout builder override form for
    // a node of our test content type, which does not have book outlines
    // enabled.
    $this->drupalGet("node/{$node->id()}/layout");
    $this->assertEquals('Current state Published', $this->cssSelect('#edit-moderation-state-0-current')[0]->getText());

    $this->submitForm([
      'moderation_state[0][state]' => 'draft',
    ], 'Save layout');

    $this->assertSession()->pageTextContains('The layout override has been saved.');

    // Now enable book for the content type and confirm we can still save
    // another draft.
    $this->setBookSettings('test_content_type', 'test_content_type');
    $this->resetAll();

    $this->drupalGet("node/{$node->id()}/layout");
    $this->assertEquals('Current state Draft', $this->cssSelect('#edit-moderation-state-0-current')[0]->getText());

    $this->submitForm([
      'moderation_state[0][state]' => 'draft',
    ], 'Save layout');

    $this->assertSession()->pageTextContains('The layout override has been saved.');
  }

}
