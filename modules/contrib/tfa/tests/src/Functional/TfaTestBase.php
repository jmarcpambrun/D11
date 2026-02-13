<?php

namespace Drupal\Tests\tfa\Functional;

use Drupal\Core\Session\AccountInterface;
use Drupal\encrypt\EncryptionProfileInterface;
use Drupal\encrypt\Entity\EncryptionProfile;
use Drupal\key\Entity\Key;
use Drupal\key\KeyInterface;
use Drupal\Tests\BrowserTestBase;

/**
 * Base class for testing the Tfa module.
 */
abstract class TfaTestBase extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A test key.
   *
   * @var \Drupal\key\KeyInterface
   */
  protected KeyInterface $testKey;

  /**
   * An encryption profile.
   *
   * @var \Drupal\encrypt\EncryptionProfileInterface
   */
  protected EncryptionProfileInterface $encryptionProfile;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'tfa_test_plugins',
    'tfa',
    'encrypt',
    'encrypt_test',
    'key',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $user = $this->drupalCreateUser([
      'access administration pages',
      'administer encrypt',
      'administer keys',
    ]);
    $this->drupalLogin($user);
    $this->generateEncryptionKey();
    $this->generateEncryptionProfile();
  }

  /**
   * Generates an encryption key.
   */
  protected function generateEncryptionKey(): void {
    $key = Key::create([
      'id' => 'testing_key_128',
      'label' => 'Testing Key 128 bit',
      'key_type' => 'encryption',
      'key_type_settings' => ['key_size' => '128'],
      'key_provider' => 'config',
      // cSpell:disable-next-line mustbesixteenbit
      'key_provider_settings' => ['key_value' => 'mustbesixteenbit'],
    ]);
    $key->save();
    $this->testKey = $key;
  }

  /**
   * Generates an Encryption profile.
   */
  protected function generateEncryptionProfile(): void {
    $encryption_profile = EncryptionProfile::create([
      'id' => 'test_encryption_profile',
      'label' => 'Test encryption profile',
      'encryption_method' => 'test_encryption_method',
      'encryption_key' => $this->testKey->id(),
    ]);
    $encryption_profile->save();
    $this->encryptionProfile = $encryption_profile;
  }

  /**
   * Reusable test for enabling a validation plugin on the configuration form.
   *
   * @param string $validation_plugin_id
   *   A validation plugin id.
   */
  protected function canEnableValidationPlugin(string $validation_plugin_id): void {
    $assert = $this->assertSession();
    $adminUser = $this->drupalCreateUser(['admin tfa settings']);
    $this->drupalLogin($adminUser);

    $this->drupalGet('admin/config/people/tfa');
    $assert->statusCodeEquals(200);
    $assert->pageTextContains('TFA Settings');

    $edit = [
      'tfa_enabled' => TRUE,
      'tfa_default_validation_plugin' => $validation_plugin_id,
      "tfa_allowed_validation_plugins[{$validation_plugin_id}]" => $validation_plugin_id,
      'encryption_profile' => $this->encryptionProfile->id(),
    ];

    $this->submitForm($edit, 'Save configuration');
    $assert->statusCodeEquals(200);
    $assert->pageTextContains('The configuration options have been saved.');
    $select_field_id = 'edit-tfa-default-validation-plugin';
    $option_field = $assert->optionExists($select_field_id, $validation_plugin_id);
    $result = $option_field->hasAttribute('selected');
    $this->assertTrue($result, "Option {$validation_plugin_id} for field {$select_field_id} is selected.");
  }

  /**
   * {@inheritdoc}
   */
  protected function drupalLogin(AccountInterface $account): void {
    // drupalLogin() calls AccountProxy::setUser() to enable future commands
    // to run as the logged-in user with the local kernel.
    $this->container->get('cache.tfa_memcache')->set('tfa_complete', (int) $account->id());
    parent::drupalLogin($account);
  }

  /**
   * {@inheritdoc}
   */
  protected function refreshVariables(): void {
    // Refresh variables purges the tfa_complete flag in drupalLogin()
    // preventing the setUser() call from succeeding. We need to persist the
    // value through the kernel.
    /** @var FALSE|object{'data': mixed} $tfa_complete */
    $tfa_complete = $this->container->get('cache.tfa_memcache')->get('tfa_complete');

    parent::refreshVariables();

    if ($tfa_complete !== FALSE) {
      if (is_int($tfa_complete->data)) {
        $this->container->get('cache.tfa_memcache')->set('tfa_complete', (int) $tfa_complete->data);
      }
    }
  }

}
