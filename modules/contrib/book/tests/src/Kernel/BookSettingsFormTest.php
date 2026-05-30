<?php

namespace Drupal\Tests\book\Kernel;

use Drupal\book\BookHelperTrait;
use Drupal\Core\Config\Schema\SchemaCheckTrait;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\book\Form\BookSettingsForm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test Book settings form.
 */
#[Group('book')]
#[CoversClass(BookSettingsForm::class)]
#[RunTestsInSeparateProcesses]
class BookSettingsFormTest extends KernelTestBase {

  use BookHelperTrait;
  use ContentTypeCreationTrait;
  use SchemaCheckTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'book',
    'book_content_type',
    'field',
    'node',
    'system',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['book', 'book_content_type', 'node']);
    $this->createContentType(['type' => 'chapter']);
    $this->createContentType(['type' => 'page']);

    // Clear default config that might include book type.
    $this->config('book.settings')
      ->set('allowed_types', [])
      ->set('book_sort', 'weight')
      ->save();
  }

  /**
   * Tests that submitted values are processed and saved correctly.
   *
   * @throws \Exception
   */
  public function testConfigValuesSavedCorrectly(): void {
    $form_state = new FormState();
    $form_state->setValues([
      'allowed_types' => [
        'page' => [
          'content_type' => 'page',
          'child_type' => 'page',
        ],
        'chapter' => [
          'content_type' => 'chapter',
          'child_type' => 'page',
        ],
      ],
      'book_sort' => 'weight',
    ]);
    $this->container->get('form_builder')->submitForm(BookSettingsForm::class, $form_state);

    $config = $this->config('book.settings');
    $allowed_types_config = $config->get('allowed_types');
    $content_types = $this->getBookContentTypes($allowed_types_config);
    $this->assertSame(['chapter', 'page'], $content_types);
    $this->assertSame('page', $allowed_types_config[0]['child_type']);
    $this->assertSame('weight', $config->get('book_sort'));
  }

  /**
   * Tests form can be saved even if previous config was invalid.
   *
   * @throws \Exception
   */
  public function testUpdateInvalidConfig(): void {
    // This is invalid config, as the child_type is not in allowed_types.
    $config = $this->config('book.settings');
    $config->set('allowed_types', [
      ['content_type' => 'book', 'child_type' => 'page'],
    ])->save();

    $form_state = new FormState();
    $form_state->setValue('allowed_types', [
      'book' => [
        'content_type' => 'book',
        'child_type' => 'page',
      ],
      'page' => [
        'content_type' => 'page',
        'child_type' => 'page',
      ],
    ]);
    $this->container->get('form_builder')->submitForm(BookSettingsForm::class, $form_state);
    $this->assertEmpty($form_state->getErrors(), 'Failed submitting config form to fix broken config.');
  }

  /**
   * Tests that valid configuration passes schema validation.
   *
   * @throws \Exception
   */
  public function testValidAllowedTypes(): void {
    $config = [
      'allowed_types' => [
        [
          'content_type' => 'book',
          'child_type' => 'book',
        ],
        [
          'content_type' => 'page',
          'child_type' => 'book',
        ],
      ],
    ];

    $typed_config = $this->container->get('config.typed');
    $result = $this->checkConfigSchema($typed_config, 'book.settings', $config);

    $this->assertTrue($result);
  }

  /**
   * Tests that invalid configuration fails schema validation.
   *
   * @throws \Exception
   */
  public function testInvalidAllowedTypes(): void {
    $config_values = [
      'allowed_types' => [
        [
          'content_type' => 'book',
          'child_type' => 'invalid_type',
        ],
      ],
    ];

    /** @var \Drupal\Core\Config\TypedConfigManagerInterface $typed_config_manager */
    $typed_config_manager = $this->container->get('config.typed');
    $typed_config = $typed_config_manager->get('book.settings');

    foreach ($config_values as $key => $value) {
      $typed_config->set($key, $value);
    }
    $violations = $typed_config->validate();

    // There should be at least one violation.
    $this->assertNotEmpty($violations);
    $expected_text = 'The child page content type';
    $found = FALSE;
    foreach ($violations as $violation) {
      if (str_contains($violation->getMessage(), $expected_text)) {
        $found = TRUE;
        break;
      }
    }
    $this->assertTrue($found);
  }

  /**
   * Tests form cannot be saved if content_type selected with no child_type.
   *
   * @throws \Exception
   */
  public function testChildTypes(): void {
    $form_state = new FormState();
    $form_state->setValues([
      'allowed_types' => [
        'page' => [
          'content_type' => 'page',
          'child_type' => NULL,
        ],
      ],
    ]);
    $this->container->get('form_builder')->submitForm(BookSettingsForm::class, $form_state);
    $errors = $form_state->getErrors();
    $this->assertArrayHasKey('allowed_types][page][child_type', $errors);
    $this->assertEquals('You must select a child type for the enabled content type.', $errors['allowed_types][page][child_type']);
  }

}
