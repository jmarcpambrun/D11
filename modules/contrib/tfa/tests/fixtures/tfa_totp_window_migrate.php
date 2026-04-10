<?php
// phpcs:ignoreFile
// cSpell:disable
/**
 * @file
 * A database agnostic dump for testing purposes.
 *
 * Provides data for migration from accepted codes to time windows.
 */

use Drupal\Core\Database\Database;

$connection = Database::getConnection();
// Ensure any tables with a serial column with a value of 0 are created as
// expected.
if ($connection->databaseType() === 'mysql') {
  $sql_mode = $connection->query("SELECT @@sql_mode;")->fetchField();
  $connection->query("SET sql_mode = '$sql_mode,NO_AUTO_VALUE_ON_ZERO'");
}

// Set schema version for TFA.
$connection->insert('key_value')
  ->fields([
    'collection',
    'name',
    'value',
  ])
  ->values([
    'collection' => 'system.schema',
    'name' => 'tfa',
    'value' => 'i:8013;',
  ])
  ->execute();

$connection->insert('users_data')
  ->fields([
    'uid',
    'module',
    'name',
    'value',
    'serialized',
  ])
  ->values([
    'uid' => 2,
    'module' => 'tfa',
    'name' => 'tfa_accepted_code_Kamur1p2CPWT2grin6fxqxFpxtX3jj_82maNmX5iK9c',
    'value' => '1748550756',
    'serialized' => 0,
  ])
  ->values([
    'uid' => 2,
    'module' => 'tfa',
    'name' => 'tfa_accepted_code_OOnDg2_oLMKof1v5-irstvj8AgPZQYDlQoHBd5q7vA8',
    'value' => '1748550794',
    'serialized' => 0,
  ])
  ->values([
    'uid' => 2,
    'module' => 'tfa',
    'name' => 'tfa_totp_seed',
    'value' => 'a:2:{s:4:"seed";s:160:"emhmZ29yZnZrZ3JyYW92Z05ITEFBUk41Ukg1SVFNMlNON0RFUDZIWDQ2WVpFTEFaSTU2TUdRNDdVSFNVRkJHV1lHNE9IT1lZN1VDQ1ZKT0xFU0dQNUZMT1BQNzdDQ0xPTEJRVkROWlZOUlY0R0NSV0UyWFhDUFY=";s:7:"created";i:1748550794;}',
    'serialized' => 1,
  ])
  ->values([
    'uid' => 2,
    'module' => 'tfa',
    'name' => 'tfa_user_settings',
    'value' => 'a:4:{s:5:"saved";i:1748550794;s:6:"status";i:1;s:4:"data";a:1:{s:7:"plugins";a:1:{s:8:"tfa_totp";s:8:"tfa_totp";}}s:18:"validation_skipped";i:0;}',
    'serialized' => 1,
  ])
  ->values([
    'uid' => 3,
    'module' => 'tfa',
    'name' => 'tfa_accepted_code_GpOQPWD7xiLEtWGlj3Jaf53IR9HUwCNWsbUpH87LtyA',
    'value' => '1748550938',
    'serialized' => 0,
  ])
  ->values([
    'uid' => 3,
    'module' => 'tfa',
    'name' => 'tfa_hotp_counter',
    'value' => '1',
    'serialized' => 0,
  ])
  ->values([
    'uid' => 3,
    'module' => 'tfa',
    'name' => 'tfa_totp_seed',
    'value' => 'a:2:{s:4:"seed";s:160:"emhmZ29yZnZrZ3JyYW92ZzRCUUNZVkQ1SlE1Tkk0VEpNMjJTSVFDNEM0SzRDWlJLVVZRN0lXNkZIQUM2TldEWEs1T0JPNFlXQjU2QkFUTFRHTkdGUk1NWE5ZTDJKWk82QkZBNUo3T0VNQTM3TFMzQUEySEVJRkQ=";s:7:"created";i:1748550938;}',
    'serialized' => 1,
  ])
  ->values([
    'uid' => 3,
    'module' => 'tfa',
    'name' => 'tfa_user_settings',
    'value' => 'a:4:{s:5:"saved";i:1748550938;s:6:"status";i:1;s:4:"data";a:1:{s:7:"plugins";a:1:{s:8:"tfa_hotp";s:8:"tfa_hotp";}}s:18:"validation_skipped";i:0;}',
    'serialized' => 1,
  ])
  ->values([
    'uid' => 4,
    'module' => 'tfa',
    'name' => 'tfa_accepted_code_iOq7F7Jij9PgtSnehFjpT9_XCIBliKlGm41wS2dgCZM',
    'value' => '1748551050',
    'serialized' => 0,
  ])
  ->values([
    'uid' => 4,
    'module' => 'tfa',
    'name' => 'tfa_totp_seed',
    'value' => 'a:2:{s:4:"seed";s:160:"emhmZ29yZnZrZ3JyYW92Z1BQWTJYRFI2WloyNEhLTk5IVURWWkFHTVlUSFlQV0VGTzdDSDZPWElDTlNKMkpNSU5CN1dPTUkzM0haSklOMkVaWlNQTjZJN1hZNVFTWTUzN1RQWFhVM0dIWFJKUllUUUdWQlBCUE4=";s:7:"created";i:1748551050;}',
    'serialized' => 1,
  ])
  ->values([
    'uid' => 4,
    'module' => 'tfa',
    'name' => 'tfa_totp_time_window',
    'value' => '58285034',
    'serialized' => 0,
  ])
  ->values([
    'uid' => 4,
    'module' => 'tfa',
    'name' => 'tfa_user_settings',
    'value' => 'a:4:{s:5:"saved";i:1748551050;s:6:"status";i:1;s:4:"data";a:1:{s:7:"plugins";a:1:{s:8:"tfa_totp";s:8:"tfa_totp";}}s:18:"validation_skipped";i:0;}',
    'serialized' => 1,
  ])
  ->values([
    'uid' => 5,
    'module' => 'tfa',
    'name' => 'tfa_user_settings',
    'value' => 'a:4:{s:5:"saved";i:1748551050;s:6:"status";i:1;s:4:"data";a:1:{s:7:"plugins";a:1:{s:8:"tfa_totp";s:8:"tfa_totp";}}s:18:"validation_skipped";i:0;}',
    'serialized' => 1,
  ])
  ->values([
    'uid' => 5,
    'module' => 'tfa',
    'name' => 'tfa_totp_seed',
    'value' => 'a:2:{s:4:"seed";s:160:"emhmZ29yZnZrZ3JyYW92Z0VBVFBPRVlBTVY0WFoyNTY3Rk1YTUczSFlLTTcyTVVWQUFaS1BTUlJMM1o2U1hXT0FWM0tMRVhUUlRIQ1g1WlY2STNLSEZSS1VLUVFEQkk1RVNONUFBSlo3T041VlAyTFU2N05YV1Y=";s:7:"created";i:1748550794;}',
    'serialized' => 1,
  ])
  ->values([
    'uid' => 6,
    'module' => 'tfa',
    'name' => 'tfa_user_settings',
    'value' => 'a:4:{s:5:"saved";i:1748551050;s:6:"status";i:1;s:4:"data";a:1:{s:7:"plugins";a:1:{s:8:"tfa_hotp";s:8:"tfa_hotp";}}s:18:"validation_skipped";i:0;}',
    'serialized' => 1,
  ])
  ->values([
    'uid' => 6,
    'module' => 'tfa',
    'name' => 'tfa_hotp_seed',
    'value' => 'a:2:{s:4:"seed";s:160:"emhmZ29yZnZrZ3JyYW92Z0VBVFBPRVlBTVY0WFoyNTY3Rk1YTUczSFlLTTcyTVVWQUFaS1BTUlJMM1o2U1hXT0FWM0tMRVhUUlRIQ1g1WlY2STNLSEZSS1VLUVFEQkk1RVNONUFBSlo3T041VlAyTFU2N05YV1Y=";s:7:"created";i:1748550794;}',
    'serialized' => 1,
  ])

  ->execute();

// Reset the SQL mode.
if ($connection->databaseType() === 'mysql') {
  $connection->query("SET sql_mode = '$sql_mode'");
}
