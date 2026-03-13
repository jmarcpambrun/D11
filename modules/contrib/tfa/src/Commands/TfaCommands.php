<?php

namespace Drupal\tfa\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Drupal\Core\Database\Connection;
use Drupal\tfa\TfaUserDataTrait;
use Drush\Commands\DrushCommands;
use Drush\Drupal\Commands\sql\SanitizePluginInterface;
use Symfony\Component\Console\Input\InputInterface;

/**
 * A Drush command file to reset or sanitize TFA for users.
 */
final class TfaCommands extends DrushCommands implements SanitizePluginInterface {
  use TfaUserDataTrait;

  /**
   * Database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * TokenManagement service.
   *
   * @var \Drupal\tfa\Commands\TfaTokenManagement
   */
  protected TfaTokenManagement $tokenManagement;

  /**
   * TfaCommands constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database service.
   * @param \Drupal\tfa\Commands\TfaTokenManagement $token_management
   *   The TFA Drush Token Management service.
   */
  public function __construct(Connection $database, TfaTokenManagement $token_management) {
    parent::__construct();
    $this->database = $database;
    $this->tokenManagement = $token_management;
  }

  /**
   * Sanitize recovery codes and user-specific TFA data.
   *
   * @hook post-command sql-sanitize
   *
   * {@inheritdoc}
   */
  public function sanitize($result, CommandData $commandData): void {
    $this->database->delete('users_data')
      ->condition('name', 'tfa_%', 'LIKE')
      ->execute();
    $this->logger()->success('Removed recovery codes and other user-specific TFA data.');
  }

  /**
   * Display summary to user before confirmation.
   *
   * @hook on-event sql-sanitize-confirms
   *
   * {@inheritdoc}
   */
  public function messages(array &$messages, InputInterface $input) {
    $messages[] = dt('Remove recovery codes and other user-specific TFA data.');
  }

  /**
   * Resets single user's TFA Data.
   *
   * @param array{name: string|null, uid: string|null, mail: string|null} $options
   *   Options to process.
   *
   * @command tfa:reset-user
   *
   * @option name A user name to reset.
   * @option uid A uid to reset.
   * @option mail A user mail address to reset.
   *
   * @aliases tfa-reset-user
   */
  public function resetUserTfaData(array $options = ['name' => NULL, 'uid' => NULL, 'mail' => NULL]): void {
    $this->tokenManagement->resetUserTfaData($options, $this->io());
  }

}
