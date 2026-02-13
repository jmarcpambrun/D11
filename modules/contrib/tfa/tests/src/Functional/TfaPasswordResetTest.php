<?php

declare(strict_types=1);

namespace Drupal\Tests\tfa\Functional;

use Drupal\Core\Test\AssertMailTrait;
use Drupal\Tests\WebAssert;
use Drupal\tfa\TfaUserDataTrait;
use Drupal\user\Entity\User;

/**
 * Tests for the tfa login process.
 *
 * @group Tfa
 */
final class TfaPasswordResetTest extends TfaTestBase {

  use AssertMailTrait {
    getMails as drupalGetMails;
  }
  use TfaUserDataTrait;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    // Enable page caching.
    $config = $this->config('system.performance');
    $config->set('cache.page.max_age', 3600);
    $config->save();

    $this->userData = $this->container->get('user.data');
    $this->drupalLogout();
  }

  /**
   * Tests the tfa one time login process.
   */
  public function testTfaOneTimeLogin(): void {
    $assert_session = $this->assertSession();

    $web_user = $this->drupalCreateUser(['setup own tfa']);
    $this->assertNotFalse($web_user);

    // Enable TFA for all authenticated user roles.
    $this->config('tfa.settings')
      ->set('enabled', 'TRUE')
      ->set('required_roles', ['authenticated'])
      ->set('allowed_validation_plugins', ['tfa_test_plugins_validation' => 'tfa_test_plugins_validation'])
      ->set('default_validation_plugin', 'tfa_test_plugins_validation')
      ->save();

    $this->tfaSaveTfaData((int) $web_user->id(), ['plugins' => 'tfa_test_plugins_validation']);

    // Check that TFA is not required but user warned when TFA not ready.
    \Drupal::state()->set('tfa.test_validation_plugin.ready', FALSE);
    $this->resetPassword($web_user);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('You are required to setup two-factor authentication. You have');
    // Change the password.
    $this->changePassword($assert_session);

    // Check that tfa is prompted when TFA is ready.
    \Drupal::state()->set('tfa.test_validation_plugin.ready', TRUE);
    $this->resetPassword($web_user);
    $assert_session->pageTextContains('Two-Factor Authentication');
    // Submit the token validation form.
    $this->submitForm([], 'Next');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('You have just used your one-time login link.');
    // Change the password.
    $this->changePassword($assert_session);
  }

  /**
   * Test session is migrated after visiting reset link.
   */
  public function testSessionFixationPrevention(): void {
    $assert_session = $this->assertSession();

    // Enable TFA for all authenticated user roles.
    $this->config('tfa.settings')
      ->set('enabled', 'TRUE')
      ->set('required_roles', ['authenticated'])
      ->set('allowed_validation_plugins', ['tfa_test_plugins_validation' => 'tfa_test_plugins_validation'])
      ->set('default_validation_plugin', 'tfa_test_plugins_validation')
      ->save();

    // Allow $web_user to setup own tfa.
    $web_user = $this->drupalCreateUser(['setup own tfa']);
    $this->assertNotFalse($web_user);
    $this->tfaSaveTfaData((int) $web_user->id(), ['plugins' => 'tfa_test_plugins_validation']);

    $this->drupalLogin($web_user);
    $this->drupalGet('user/password');
    $edit = ['name' => $web_user->getAccountName()];
    $this->submitForm($edit, 'Submit');
    // Get the one time reset URL form the email.
    $resetURL = $this->getResetURL() . '/login';

    // Reset any session data and set a fixated session id.
    $this->getSession()->restart();
    $this->getSession()->setCookie($this->getSessionName(), '1');

    $this->drupalGet($resetURL);
    // Ensure this is tfa\Form\EntryForm.
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Two-Factor Authentication');

    // Note: drupalGet() followed the 302 redirect. We are testing after
    // redirect followed, ideally we would test the redirect response.
    $this->assertNotNull($this->getSessionCookies()->getCookieByName($this->sessionName));
    $after_reset_page_session_value = $this->getSessionCookies()->getCookieByName($this->sessionName)->getValue();
    $this->assertNotEquals('1', $after_reset_page_session_value, "Ensure session migrated");
  }

  /**
   * Retrieves password reset email and extracts the login link.
   */
  private function getResetUrl(): string {
    // Assume the most recent email.
    $_emails = $this->drupalGetMails();
    $email = end($_emails);
    $urls = [];
    preg_match('#.+user/reset/.+#', $email['body'], $urls);
    $this->assertNotEmpty($urls);
    $path = parse_url($urls[0], PHP_URL_PATH);
    $this->assertIsString($path);
    $string_position = strpos($path, 'user/reset/');
    $this->assertNotFalse($string_position);
    $reset_path = substr($path, $string_position);
    return $reset_path;
  }

  /**
   * Reset password login process.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user who need to reset the password.
   */
  private function resetPassword(User $user): void {
    $this->drupalGet('user/password');
    $edit = ['name' => $user->getAccountName()];
    $this->submitForm($edit, 'Submit');
    // Get the one time reset URL form the email.
    $resetURL = $this->getResetUrl() . '/login';
    // Login via one time login URL
    // and check if the TFA presented.
    $this->drupalGet($resetURL);
  }

  /**
   * Action to change user own password.
   *
   * @param \Drupal\Tests\WebAssert $assert_session
   *   Web assert object.
   * @param bool $logout
   *   If true, logout the user at the end.
   */
  private function changePassword(WebAssert $assert_session, bool $logout = TRUE): void {
    // Change the password.
    $password = \Drupal::service('password_generator')->generate();
    $edit = ['pass[pass1]' => $password, 'pass[pass2]' => $password];
    $this->submitForm($edit, 'Save');
    $assert_session->pageTextContains('The changes have been saved.');
    if ($logout) {
      $this->drupalLogout();
    }
  }

}
