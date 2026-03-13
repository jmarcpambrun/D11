<?php

namespace Drupal\mail_box_management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\mail_box_management\Form\ConfigurationForm;
use Drupal\mail_box_management\Form\MailBoxManagementSettingForm;
use Drupal\mail_box_management\Plugin\ThemeTemplateLoader;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * MailboxManagement contains class MailboxManagement.
 *
 * @file
 * MailboxManagement contains class MailboxManagement
 */

/**
 * MailboxManagement extends ControllerBase.
 *
 * @class
 * MailboxManagement extends ControllerBase.
 */
class MailboxManagement extends ControllerBase {

  /**
   * Theme loader object.
   *
   * @var \Drupal\mail_box_management\Plugin\ThemeTemplateLoader|mixed
   */
  private ThemeTemplateLoader $themeTemplateLoader;

  public function __construct() {
    $this->themeTemplateLoader = mail_box_management_service('mail_box_management.theme');
  }

  /**
   * Returns configuration page.
   *
   * @return array
   *   Returns array of markup and form.
   *
   * @throws \Exception
   */
  public function configuration(): array {
    $template = 'mail-box-management-configuration';
    $form = $this->formBuilder()->getForm(ConfigurationForm::class);
    return [
      '#markup' => $this->themeTemplateLoader->getThemeTemplateContent($template, ['form' => $form]),
      '#attached' => [
        'library' => [
          'assets_management',
        ],
      ],
    ];
  }

  /**
   * Handles Settings page of this module.
   *
   * @return array
   *   Returns array of markup.
   */
  public function settingsForm(): array {
    $template = 'mail-box-management-settings';
    $form = $this->formBuilder()->getForm(MailBoxManagementSettingForm::class);
    return [
      '#markup' => $this->themeTemplateLoader->getThemeTemplateContent($template, ['form' => $form]),
      '#attached' => [
        'library' => [
          'assets_management',
        ],
      ],
    ];
  }

  /**
   * Delete configuration controller.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect returned.
   */
  public function mailboxManagementMailServerDeletions(Request $request): array|RedirectResponse {
    $uid = $request->get('owner_id');
    $config_helper = mail_box_management_service('mail_box_management.config');
    if (!empty($uid)) {
      if ($config_helper->delete($uid)) {
        mail_box_management_service('messenger')->addMessage("Configuration successfully deleted.");
      }
      else {
        mail_box_management_service('messenger')->addError("Unable to delete configuration.");
      }
    }
    return new RedirectResponse(Url::fromRoute('mail_box_management.configuration')->toString());
  }

}
