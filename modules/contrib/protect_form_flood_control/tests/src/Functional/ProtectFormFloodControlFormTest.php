<?php

namespace Drupal\Tests\protect_form_flood_control\Functional;

use Drupal\comment\Tests\CommentTestTrait;
use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;
use Drupal\contact\Entity\ContactForm;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\UserInterface;

/**
 * Test Protect from flood control spam protection functionality.
 *
 * @group protect_form_flood_control
 */
class ProtectFormFloodControlFormTest extends BrowserTestBase {

  use CommentTestTrait;
  use StringTranslationTrait;

  /**
   * Admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * Site visitor.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $webUser;

  /**
   * Node object.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * Default theme.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['protect_form_flood_control', 'node', 'comment', 'contact'];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    // Enable modules required for this test.
    parent::setUp();

    // Set up required module configuration.
    $config = \Drupal::configFactory()->getEditable('protect_form_flood_control.settings');
    $general_config = [
      'protect_all' => FALSE,
      'window' => 60,
      'threshold' => 2,
      'protected_ids' => ['comment_*', 'user_register_form'],
      'unprotected_ids' => [],
      'whitelist' => [],
      'log' => FALSE,
    ];
    $config->set('general', $general_config);
    $config->save();

    // Set up other required configuration.
    $user_config = \Drupal::configFactory()->getEditable('user.settings');
    $user_config->set('verify_mail', TRUE);
    $user_config->set('register', UserInterface::REGISTER_VISITORS);
    $user_config->save();

    // Create an Article node type.
    if ($this->profile != 'standard') {
      $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
      // Create comment field on article.
      $this->addDefaultCommentField('node', 'article');
    }

    // Set up admin user.
    $this->adminUser = $this->drupalCreateUser([
      'administer protect form flood control',
      'bypass protect form flood control',
      'administer content types',
      'administer users',
      'access comments',
      'post comments',
      'skip comment approval',
      'administer comments',
    ]);

    // Set up web user.
    $this->webUser = $this->drupalCreateUser([
      'access comments',
      'post comments',
      'create article content',
      'access site-wide contact form',
    ]);

    // Set up example node.
    $this->node = $this->drupalCreateNode([
      'type' => 'article',
      'comment' => CommentItemInterface::OPEN,
    ]);
  }

  /**
   * Make sure user login form is not protected.
   */
  public function testUserLoginNotProtected() {
    $this->drupalGet('user');
    $this->assertSession()->responseNotContains('name="protect_form_flood_control"');
  }

  /**
   * Test user registration (anonymous users).
   */
  public function testProtectRegisterUser() {
    $this->drupalGet('user/register');
    $this->assertSession()->responseContains('name="protect_form_flood_control"');
    // Set up form and submit it.
    $edit['name'] = $this->randomMachineName();
    $edit['mail'] = $edit['name'] . '@example.com';
    $this->drupalGet('user/register');
    $this->submitForm($edit, $this->t('Create new account'));
    // Form should have been submitted successfully.
    $this->assertSession()->pageTextContains('A welcome message with further instructions has been sent to your email address.');

    $edit['name'] = $this->randomMachineName();
    $edit['mail'] = $edit['name'] . '@example.com';
    $this->drupalGet('user/register');
    $this->submitForm($edit, $this->t('Create new account'));
    // Form should have been submitted successfully.
    $this->assertSession()->pageTextContains('A welcome message with further instructions has been sent to your email address.');

    $edit['name'] = $this->randomMachineName();
    $edit['mail'] = $edit['name'] . '@example.com';
    $this->drupalGet('user/register');
    $this->submitForm($edit, $this->t('Create new account'));

    $window = \Drupal::service('date.formatter')->formatInterval(60);
    $this->assertSession()->pageTextContains("You cannot submit the form more than 2 times in $window. Please, try again later.");
  }

  /**
   * Test comment form protection.
   */
  public function testProtectCommentForm() {
    $comment = 'Test comment.';
    // Log in the web user.
    $this->drupalLogin($this->webUser);

    // Set up form and submit it.
    $edit["comment_body[0][value]"] = $comment;
    $this->drupalGet('comment/reply/node/' . $this->node->id() . '/comment');
    $this->submitForm($edit, $this->t('Save'));
    $this->assertSession()->pageTextContains('Your comment has been queued for review');
    $this->drupalGet('comment/reply/node/' . $this->node->id() . '/comment');

    $this->submitForm($edit, $this->t('Save'));
    $this->assertSession()->pageTextContains('Your comment has been queued for review');
    $this->drupalGet('comment/reply/node/' . $this->node->id() . '/comment');

    $this->submitForm($edit, $this->t('Save'));
    $window = \Drupal::service('date.formatter')->formatInterval(60);
    $this->assertSession()->pageTextContains("You cannot submit the form more than 2 times in $window. Please, try again later.");
  }

  /**
   * Test for comment form bypass.
   */
  public function testProtectCommentFormBypass() {
    // Log in the admin user.
    $this->drupalLogin($this->adminUser);
drupal_flush_all_caches();
    // Get the comment reply form and ensure there's no 'url' field.
    $this->drupalGet('comment/reply/node/' . $this->node->id() . '/comment');
    $this->assertSession()->responseNotContains('name="protect_form_flood_control"');
  }

  /**
   * Test protection on the Contact form.
   */
  public function testProtectContactForm() {
    $this->drupalLogin($this->adminUser);

    // Create a Website feedback contact form.
    $feedback_form = ContactForm::create([
      'id' => 'feedback',
      'label' => 'Website feedback',
      'recipients' => [],
      'reply' => '',
      'weight' => 0,
    ]);
    $feedback_form->save();
    $contact_settings = \Drupal::configFactory()->getEditable('contact.settings');
    $contact_settings->set('default_form', 'feedback')->save();

    $this->drupalLogin($this->webUser);
    $this->drupalGet('contact/feedback');
    $this->assertSession()->responseNotContains('name="protect_form_flood_control"');

    // Update module configuration.
    $config = \Drupal::configFactory()->getEditable('protect_form_flood_control.settings');
    $forms_config = [
      [
        'ids' => ['contact_*'],
        'window' => 120,
        'threshold' => 1,
      ],
    ];
    $config->set('forms', $forms_config);
    $config->save();

    $this->drupalGet('contact/feedback');
    $this->assertSession()->responseContains('name="protect_form_flood_control"');
  }

}
