<?php

namespace Drupal\mail_box_management\Controller;

use Drupal\Core\Url;
use Drupal\mail_box_management\Plugin\ThemeTemplateLoader;
use Drupal\mail_box_management\Server\Imap\EmailContentManager;
use Drupal\mail_box_management\Server\Imap\ImapConnection;
use Drupal\mail_box_management\Server\Imap\MailBoxStorage;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * MailboxManagementPanel file contains MailboxManagementPanel class.
 *
 * @file
 * MailboxManagementPanel contains class MailboxManagementPanel
 */

/**
 * MailboxManagementPanel responsible for mailbox window page.
 *
 * @class
 * MailboxManagementPanel responsible for mailbox window page.
 */
class MailboxManagementPanel {

  /**
   * Theme loader object.
   *
   * @var \Drupal\mail_box_management\Plugin\ThemeTemplateLoader|mixed
   */
  private ThemeTemplateLoader $themeTemplateLoader;

  /**
   * ImapConnection object.
   *
   * @var \Drupal\mail_box_management\Server\Imap\ImapConnection|mixed
   */
  private ImapConnection $connection;

  /**
   * Initialize MailboxManagementPanel class object.
   */
  public function __construct() {
    $this->themeTemplateLoader = mail_box_management_service('mail_box_management.theme');
    $this->connection = mail_box_management_service('mail_box_management.imap_connector');
  }

  /**
   * Controller handler.
   *
   * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
   *   Markup array or redirect response is return.
   */
  public function mailboxManagement(): array|RedirectResponse {
    $content = $this->themeTemplateLoader->getThemeTemplateContent('mail-box-management-panel');
    if (is_null($content) || $content === 'NO-CONNECTION') {
      mail_box_management_service('messenger')->addError("Connection to IMAP did not connect to mailbox management service.");
      $uri = Url::fromRoute('mail_box_management.configuration');
      return new RedirectResponse($uri->toString());
    }
    return [
      '#markup' => $content,
    ];
  }

  /**
   * Mail content builder controller.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Json object is returned.
   */
  public function mailboxManagementContent(Request $request): JsonResponse {
    $content = json_decode($request->getContent(), TRUE);
    $mailbox = $content['box_name'] ?? NULL;
    $msgno = $content['msgno'] ?? NULL;

    if (empty($mailbox) && empty($msgno)) {
      return new JsonResponse(['status' => FALSE, 'msg' => 'mailbox and msgno are required'], 404);
    }
    $local_storage = mail_box_management_service('mail_box_management.local.storage');
    $uid = mail_box_management_service('current_user')->id();
    $content_string = $local_storage->getTemplateData("$mailbox._.$msgno._.$uid");
    if ($content_string) {
      return new JsonResponse(['status' => TRUE, 'content' => $content_string, 'data' => []], 200);
    }
    $mail = new EmailContentManager($this->connection);
    $mail_found = $mail->getByLoaderCall($mailbox, $msgno, $uid);
    $content = $this->themeTemplateLoader->getThemeTemplateContent('mail-box-management-content-view', ['emails' => $mail_found]);
    $local_storage->templateContentSaver("$mailbox._.$msgno._.$uid", $content);
    return new JsonResponse(['status' => TRUE, 'content' => $content->__toString(), 'data' => $mail_found], 200);
  }

  /**
   * Controller for flags handling.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Returns json object.
   */
  public function mailboxManagementContentFlag(Request $request): JsonResponse {
    $content = json_decode($request->getContent(), TRUE);
    $mailbox = $content['box_name'] ?? NULL;
    $msgno = $content['msgno'] ?? NULL;
    $flag = $content['flag'] ?? NULL;
    if (empty($mailbox) && empty($msgno) && empty($flag)) {
      return new JsonResponse(['status' => FALSE, 'msg' => 'mailbox and msgno are required'], 404);
    }
    $mail = new EmailContentManager($this->connection);
    return new JsonResponse(['status' => $mail->updateMessageFlags($mailbox, $msgno, strtolower($flag))], 200);
  }

  /**
   * Controller for deleting email.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Return Json response.
   */
  public function mailboxManagementContentDelete(Request $request): JsonResponse {
    $content = json_decode($request->getContent(), TRUE);
    $mailbox = $content['box_name'] ?? NULL;
    $msgno = $content['msgno'] ?? NULL;
    if (empty($mailbox) && empty($msgno)) {
      return new JsonResponse(['status' => FALSE, 'msg' => 'mailbox and msgno are required'], 404);
    }
    $mail = new EmailContentManager($this->connection);
    return new JsonResponse(['status' => $mail->deleteEmail($mailbox, $msgno)], 200);
  }

  /**
   * Controller for worker.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Json is returns.
   *
   * @throws \Exception
   */
  public function mailboxManagementWorkerHandler(Request $request): JsonResponse {
    $content = json_decode($request->getContent(), TRUE);
    $mailbox = $content['mailbox_name'] ?? NULL;
    if (empty($mailbox)) {
      return new JsonResponse(['status' => FALSE, 'msg' => 'mailbox are required'], 404);
    }

    $mail = new EmailContentManager($this->connection);
    $current_user = mail_box_management_service('current_user');
    $mailbox_contents = $mail->worker($mailbox, $current_user->id());
    if (empty($mailbox_contents)) {
      return new JsonResponse([
        'status' => TRUE,
        'list' => [
          $mailbox => [],
        ],
      ], 200);
    }

    $all_contents = [];
    foreach ($mailbox_contents as $mailbox_content) {
      $content = $this->themeTemplateLoader->getThemeTemplateContent('mail-box-management-content-view', ['emails' => $mailbox_content]);
      $mailbox_content = reset($mailbox_content);
      $all_contents[] = [
        trim($mailbox_content['header']['Msgno']) =>
          [
            'status' => TRUE,
            'content' =>
            $content->__toString(),
            'data' => $mailbox_content,
          ],
      ];
    }
    return new JsonResponse([
      'status' => TRUE,
      'list' => [
        $mailbox => $all_contents,
      ],
    ], 200);
  }

  /**
   * Refreshing ui controller.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Json response is returned.
   */
  public function mailboxManagementUiRefresh(): JsonResponse {
    $content = $this->themeTemplateLoader->getThemeTemplateContent('mail-box-management-on-refresh');
    if (empty($content->__toString())) {
      return new JsonResponse(['status' => FALSE, 'msg' => 'ui refreshing failed'], 404);
    }
    return new JsonResponse(['status' => TRUE, 'content' => $content->__toString()]);
  }

  /**
   * Sending mail Controller.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Returns json object.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function mailboxManagementComposeMail(Request $request): JsonResponse {

    $content = json_decode($request->getContent(), TRUE);
    if (empty($content['recipient']) || empty($content['subject']) || empty($content['content'])) {
      return new JsonResponse(['status' => FALSE, 'msg' => 'recipient, subject and body content are required'], 404);
    }
    $mail = new EmailContentManager($this->connection);
    $result = $mail->sendMail(
      [
        'recipient' => $content['recipient'],
        'subject' => $content['subject'],
        'message' => $content['content'],
        'attachments' => $content['attachments'],
      ]
    );
    return new JsonResponse(['status' => $result, 'msg' => $mail->message], 200);
  }

  /**
   * Mailbox creation controller.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Json response is returned.
   */
  public function mailboxManagementMailBoxCreation(Request $request): JsonResponse {
    $content = json_decode($request->getContent(), TRUE);
    if (empty($content['title']) || empty($content['type'])) {
      return new JsonResponse(['status' => FALSE, 'msg' => 'title are required'], 404);
    }
    $title = str_replace(' ', '', $content['title']);
    $mailbox = new MailBoxStorage($this->connection);
    if ($content['type'] === 'label') {
      $title = "Labels." . $title;
    }
    return new JsonResponse(['status' => $mailbox->createMilBox($title), 'msg' => 'created successfully'], 200);
  }

  /**
   * Copy and Move Controller.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Json response is returned.
   */
  public function mailboxManagementMailBoxMailTopAction(Request $request): JsonResponse {

    $content = json_decode($request->getContent(), TRUE);
    if (empty($content['type']) || empty($content['msgno']) || empty($content['destination']) || empty($content['source'])) {
      return new JsonResponse(['status' => FALSE, 'msg' => 'destination are required'], 404);
    }
    $mail = new EmailContentManager($this->connection);
    $result = FALSE;
    if ($content['type'] === 'copy') {
      $result = $mail->copyEmails($content['msgno'], $content['source'], $content['destination']);
    }
    elseif ($content['type'] === 'move') {
      $result = $mail->moveEmails($content['msgno'], $content['source'], $content['destination']);
    }

    if ($result) {
      return new JsonResponse(['status' => TRUE, 'msg' => $mail->message], 200);
    }
    return new JsonResponse(['status' => FALSE, 'msg' => $mail->message, 'data' => $content], 404);
  }

  /**
   * Replay email controller.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Json response is returned.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function mailboxManagementComposeMailReply(Request $request): JsonResponse {

    $content = json_decode($request->getContent(), TRUE);
    if (empty($content['box_name']) || empty($content['content']) || empty($content['message_id']) || empty($content['subject']) || empty($content['msgno']) || empty($content['recipient'])) {
      return new JsonResponse(['status' => FALSE, 'msg' => 'Reply could`t go through something went wrong'], 404);
    }
    $mail = new EmailContentManager($this->connection);
    $data = $mail->getContent($content['box_name'], $content['msgno']);
    $body_content = $data['body']['content'] ?? NULL;
    $html = "<strong>Reply: " . ($data['header']['Subject'] ?? NULL) . ":</strong><br/><br>";
    $html .= "<blockquote type='cite' style='padding: 0 0.4em; border-left: #1010ff 2px solid; margin: 0;'>{$content['content']}</blockquote>";
    $result = $mail->sendReplyMail([
      'message_id' => $content['message_id'],
      'subject' => "Re:" . $data['header']['Subject'],
      'message' => "<div style='display: block;'>{$body_content}</div>" . $html,
      'recipient' => $content['recipient'],
      'attachments' => $content['attachments'],
    ]);
    return new JsonResponse(['status' => $result, 'msg' => $mail->message], 200);
  }

  /**
   * Search emails.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Found data is returned.
   *
   * @throws \Exception
   */
  public function mailboxManagementMailSearch(Request $request): JsonResponse|array {
    $content = $request->get('i');
    $mailbox = $request->get('mailbox');
    if (empty($content) || empty($mailbox)) {
      return new JsonResponse(['status' => FALSE, 'msg' => 'Search input, mailbox name are required'], 404);
    }
    $mail = new EmailContentManager($this->connection);
    $data = [];
    if (filter_var($content, FILTER_VALIDATE_EMAIL)) {
      $data = $mail->searchByMail($mailbox, $content);
    }
    else {
      $data = $mail->searchBySubject($mailbox, $content);
    }
    $content_build = $this->themeTemplateLoader->getThemeTemplateContent('mail-box-management-mail-listing', ['headers' => $data]);
    return new JsonResponse(['status' => TRUE, 'content' => $content_build, 'total' => count($data)], 200);
  }

}
