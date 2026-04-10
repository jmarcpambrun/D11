<?php

namespace Drupal\Tests\tfa\Functional\UpdateTests;

/**
 * Test basic E2E operation of TOTP time window conversion post_update hook.
 *
 * @group tfa
 *
 * @covers ::tfa_post_update_convert_totp_from_accepted_codes
 */
class SetTotpTimeWindowFromAcceptedCodesTest extends UpdateTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    parent::setDatabaseDumpFiles();
    $this->databaseDumpFiles[] = __DIR__ . '/../../../fixtures/tfa_totp_window_migrate.php';
  }

  /**
   * Validate totp window set based on accepted codes.
   */
  public function testTotpTimeWindowConversion(): void {
    $this->runUpdates();
    $user_data = \Drupal::service('user.data');
    // User without any TFA data should not have a window set.
    $this->assertEquals(NULL, $user_data->get('tfa', 1, 'tfa_totp_time_window'));
    // User with multiple codes uses latest time.
    $this->assertEquals(58285028, $user_data->get('tfa', 2, 'tfa_totp_time_window'));
    // User with only single code uses the time of saved code.
    $this->assertEquals(58285033, $user_data->get('tfa', 3, 'tfa_totp_time_window'));
    // User with a recorded window preserves the saved time.
    $this->assertEquals(58285034, $user_data->get('tfa', 4, 'tfa_totp_time_window'));
    // User with TOTP token seed and no accepted codes will use current
    // request time.
    $this->assertGreaterThan(58317204, $user_data->get('tfa', 5, 'tfa_totp_time_window'));
    // User without TOTP token seed will not set window.
    $this->assertEquals(NULL, $user_data->get('tfa', 6, 'tfa_totp_time_window'));

  }

}
