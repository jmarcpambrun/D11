<?php

namespace Drupal\mail_box_management\Theme;

use Drupal\Core\Theme\ThemeNegotiatorInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Class MailAccountOwnerThemeNegotiator.
 *
 * Sets a specific theme for 'mail_account_owner' role users.
 */
class MailAccountOwnerThemeNegotiator implements ThemeNegotiatorInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a new MailAccountOwnerThemeNegotiator.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(AccountInterface $current_user) {
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    return $this->currentUser->hasRole('mail_account_owner');
  }

  /**
   * {@inheritdoc}
   */
  public function determineActiveTheme(RouteMatchInterface $route_match): ?string {

    $module = mail_box_management_service('module_handler')
      ->getModule('mail_box_management');
    $path = $module->getPath();

    $config_helper = mail_box_management_service('mail_box_management.config');
    $theme_setting = $config_helper->get('mailbox_theme')?->get('mailbox_theme');

    $routing = "$path/mail_box_management.routing.yml";
    if (file_exists($routing)) {
      $routes = array_keys(Yaml::parseFile($routing));
      $current_route = $route_match->getRouteName();
      if (in_array($current_route, $routes)) {
        return empty($theme_setting) ? NULL : $theme_setting;
      }
    }
    return NULL;
  }

}
