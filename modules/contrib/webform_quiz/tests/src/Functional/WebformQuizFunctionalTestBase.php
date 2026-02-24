<?php

namespace Drupal\Tests\webform_quiz\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Functional test base class.
 *
 * @ingroup webform_quiz
 *
 * @group webform_quiz
 */
abstract class WebformQuizFunctionalTestBase extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['webform_quiz'];

  /**
   * A user with permission to administer site configuration.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->user = $this->drupalCreateUser(['administer site configuration']);
    $this->drupalLogin($this->user);
  }

}
