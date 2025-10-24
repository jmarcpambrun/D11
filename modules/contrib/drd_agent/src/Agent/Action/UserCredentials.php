<?php

namespace Drupal\drd_agent\Agent\Action;

use Drupal\user\Entity\User;
use Drupal\user\UserNameValidator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'UserCredentials' code.
 */
class UserCredentials extends Base {

  /**
   * The user name validator.
   *
   * @var \Drupal\user\UserNameValidator
   */
  protected UserNameValidator $userNameValidator;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->userNameValidator = $container->get('user.name_validator');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): array {
    $args = $this->getArguments();
    /** @var \Drupal\user\Entity\User|null $account */
    $account = User::load($args['uid']);
    if (!$account) {
      $this->messenger->addMessage('User does not exist.', 'error');
    }
    else {
      $this->setUsername($account, $args);
      $this->setPassword($account, $args);
      $this->setStatus($account, $args);
      try {
        $account->save();
      }
      catch (\Exception $ex) {
        $this->messenger->addMessage('Changing user credentials failed.', 'error');
      }
    }
    return [];
  }

  /**
   * Callback to set the new username if it is not taken yet.
   *
   * @param \Drupal\user\Entity\User $account
   *   User account which should be changed.
   * @param array $args
   *   Array of arguments.
   */
  private function setUsername(User $account, array $args): void {
    if (empty($args['username'])) {
      return;
    }
    $check = $this->userNameValidator->validateName($args['username']);
    if ($check->count()) {
      $this->messenger->addMessage($check->get(0)->getMessage(), 'error');
      return;
    }
    /** @var \Drupal\user\Entity\User|null $user */
    $user = user_load_by_name($args['username']);
    if ($user && $user->uid !== $args['uid']) {
      $this->messenger->addMessage('Username already taken.', 'error');
      return;
    }
    $account->setUsername($args['username']);
  }

  /**
   * Callback to set the new password.
   *
   * @param \Drupal\user\Entity\User $account
   *   User account which should be changed.
   * @param array $args
   *   Array of arguments.
   */
  private function setPassword(User $account, array $args): void {
    if (empty($args['password'])) {
      return;
    }
    $account->setPassword($args['password']);
  }

  /**
   * Callback to set the status of the user account.
   *
   * @param \Drupal\user\Entity\User $account
   *   User account which should be changed.
   * @param array $args
   *   Array of arguments.
   */
  private function setStatus(User $account, array $args): void {
    if (!isset($args['status'])) {
      return;
    }
    if ($args['status']) {
      $account->activate();
    }
    else {
      $account->block();
    }
  }

}
