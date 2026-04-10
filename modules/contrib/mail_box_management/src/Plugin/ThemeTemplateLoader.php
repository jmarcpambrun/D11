<?php

namespace Drupal\mail_box_management\Plugin;

use Drupal\Component\Render\MarkupInterface;
use Drupal\mail_box_management\Server\Imap\MailBoxStorage;

/**
 * File ThemeTemplateLoader for loading template contents.
 *
 * @file
 * ThemeTemplateLoader contains class ThemeTemplateLoader
 */

/**
 * Class ThemeTemplateLoader responsible for twig handling.
 *
 * @class
 * ThemeTemplateLoader for handling twig theme files.
 */
readonly class ThemeTemplateLoader {

  /**
   * ConfigurationHelper object.
   *
   * @var \Drupal\mail_box_management\Plugin\ConfigurationHelper|mixed
   */
  private ConfigurationHelper $configurationHelper;

  /**
   * Initialize the ThemeTemplateLoader object.
   */
  public function __construct() {
    $this->configurationHelper = mail_box_management_service('mail_box_management.config');
  }

  /**
   * Getting theme built contents.
   *
   * @param string $templateName
   *   Template name.
   * @param array $variables
   *   Data to pass to twig.
   *
   * @return \Drupal\Component\Render\MarkupInterface|string|null
   *   Returns markup object or null if theme template not located.
   */
  public function getThemeTemplateContent(string $templateName, array $variables = []): MarkupInterface|string|null {
    if ($templateName !== "mail-box-management-configuration" && $templateName !== "mail-box-management-settings" && $templateName !== "mail-box-management-mail-listing") {
      $request_stack = mail_box_management_service('request_stack');
      $variables['mailbox']['host'] = $request_stack->getCurrentRequest()->getSchemeAndHttpHost();
      $variables['mailbox']['path'] = mail_box_management_service('module_handler')->getModule('mail_box_management')->getPath();
      $variables['mailbox']['connection'] = [
        'title' => $this->configurationHelper->getByCurrentUser('connection_type_title'),
        'type' => $this->configurationHelper->getByCurrentUser('connection_type'),
      ];
      $imap = mail_box_management_service('mail_box_management.imap_connector');
      if (empty($imap->imapConnection)) {

        return "NO-CONNECTION";
      }
      $boxes = new MailBoxStorage($imap);
      $mailboxes = $boxes->getMailboxes();
      $labels = [];

      $mailboxes_statuses = [];
      $headers_content = [];
      if (!empty($mailboxes)) {
        foreach ($mailboxes as $key => $mailbox) {
          $mailboxes_statuses[$key] = $boxes->checkMailboxStatus($mailbox);
          $headers_content[$key] = $boxes->getHeaders($mailbox);
        }
      }
      $variables['mailbox']['imap']['mailboxes_statuses'] = $mailboxes_statuses;
      $variables['mailbox']['imap']['headers_content'] = $headers_content;
      $variables['mailbox']['imap']['active_box']['Inbox'] = TRUE;

      foreach ($mailboxes as $key => $mailbox) {
        $variables['mailbox']['imap']['range'][$key] = $boxes->getTotalRange($mailbox);
      }

      foreach ($mailboxes as $key => $mailbox) {
        if (strpos($mailbox, 'Labels')) {
          $labels[$key] = $mailbox;
          unset($mailboxes[$key]);
        }
      }
      $variables['mailbox']['imap']['mailboxes'] = $mailboxes;
      $variables['mailbox']['imap']['labels'] = $labels;
    }

    // dump($headers_content);
    $elements = [
      '#theme' => $templateName,
      '#title' => '',
      '#content' => $variables,
      '#attached' => [
        'library' => [
          'assets_management',
        ],
      ],
    ];

    /**@var \Drupal\Core\Render\Renderer $render **/
    $render = mail_box_management_service('renderer');
    return $render->renderRoot($elements);
  }

}
