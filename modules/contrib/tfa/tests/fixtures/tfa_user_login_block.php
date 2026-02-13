<?php
// phpcs:ignoreFile
// cSpell:disable
/**
 * @file
 * A database agnostic dump for testing purposes.
 */

use Drupal\Core\Database\Database;

$connection = Database::getConnection();
// Ensure any tables with a serial column with a value of 0 are created as
// expected.
if ($connection->databaseType() === 'mysql') {
  $sql_mode = $connection->query("SELECT @@sql_mode;")->fetchField();
  $connection->query("SET sql_mode = '$sql_mode,NO_AUTO_VALUE_ON_ZERO'");
}

// Add a tfa_user_login_block
$connection->insert('config')
  ->fields([
    'collection',
    'name',
    'data',
  ])
  ->values([
    'collection' => '',
    'name' => 'block.block.olivero_tfauserlogin',
    'data' => 'a:12:{s:4:"uuid";s:36:"e3e872c7-5cd7-439a-8eff-d30d924f8751";s:8:"langcode";s:2:"en";s:6:"status";b:1;s:12:"dependencies";a:2:{s:6:"module";a:1:{i:0;s:3:"tfa";}s:5:"theme";a:1:{i:0;s:7:"olivero";}}s:2:"id";s:20:"olivero_tfauserlogin";s:5:"theme";s:7:"olivero";s:6:"region";s:7:"sidebar";s:6:"weight";i:0;s:8:"provider";N;s:6:"plugin";s:20:"tfa_user_login_block";s:8:"settings";a:4:{s:2:"id";s:20:"tfa_user_login_block";s:5:"label";s:14:"Tfa User login";s:13:"label_display";s:7:"visible";s:8:"provider";s:3:"tfa";}s:10:"visibility";a:0:{}}',
  ])
  ->execute();

// Reset the SQL mode.
if ($connection->databaseType() === 'mysql') {
  $connection->query("SET sql_mode = '$sql_mode'");
}
