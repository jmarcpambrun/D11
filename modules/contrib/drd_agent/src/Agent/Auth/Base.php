<?php

namespace Drupal\drd_agent\Agent\Auth;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Drupal\user\UserAuthInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Remote DRD Auth Methods.
 */
abstract class Base implements BaseInterface {

  /**
   * All the settings of the implementing authentication method.
   *
   * @var array
   */
  protected array $storedSettings;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * The state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * The user authentication.
   *
   * @var \Drupal\user\UserAuthInterface
   */
  protected UserAuthInterface $userAuth;

  /**
   * Base constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_User
   *   The current user.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state.
   * @param \Drupal\user\UserAuthInterface $user_auth
   *   The user authentication.
   */
  final public function __construct(AccountInterface $current_User, StateInterface $state, UserAuthInterface $user_auth) {
    $this->currentUser = $current_User;
    $this->state = $state;
    $this->userAuth = $user_auth;
  }

  /**
   * Create a Base instance.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   */
  public static function create(ContainerInterface $container): Base {
    return new static(
      $container->get('current_user'),
      $container->get('state'),
      $container->get('user.auth')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function getMethods(ContainerInterface $container): array {
    $methods = [
      'username_password' => 'UsernamePassword',
      'shared_secret' => 'SharedSecret',
    ];
    foreach ($methods as $key => $class) {
      $classname = "\\Drupal\\drd_agent\\Agent\\Auth\\$class";
      $methods[$key] = $classname::create($container);
    }
    return $methods;
  }

  /**
   * {@inheritdoc}
   */
  final public function validateUuid(string $uuid): bool {
    $authorised = $this->state->get('drd_agent.authorised', []);
    if (empty($authorised[$uuid])) {
      return FALSE;
    }
    $this->storedSettings = (array) $authorised[$uuid]['authsetting'];
    return TRUE;
  }

}
