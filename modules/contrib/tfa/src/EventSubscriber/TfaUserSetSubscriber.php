<?php

declare(strict_types=1);

namespace Drupal\tfa\EventSubscriber;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Render\BareHtmlPageRendererInterface;
use Drupal\Core\Session\AccountEvents;
use Drupal\Core\Session\AccountSetEvent;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\tfa\Exceptions\TfaAccessDenied;
use Drupal\tfa\TfaLoginContextFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Enforce TFA services processed authentication.
 *
 * @internal
 */
final class TfaUserSetSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Construct a new TfaUserSetSubscriber subscriber.
   */
  public function __construct(
    protected SessionInterface $session,
    #[Autowire(service: 'logger.channel.tfa')] protected LoggerChannelInterface $loggerChannel,
    protected ConfigFactoryInterface $configFactory,
    #[Autowire(service: 'cache.tfa_memcache')] protected CacheBackendInterface $memoryCache,
    TranslationInterface $string_translation,
    protected BareHtmlPageRendererInterface $bareRenderer,
    protected TfaLoginContextFactory $loginContextFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->setStringTranslation($string_translation);
  }

  /**
   * Check that TFA has processed authentication prior to completing setUser().
   *
   * @throws \Drupal\tfa\Exceptions\TfaAccessDenied
   *   When an AccountSetEvent has been called for a UID that has not been
   *   processed through TFA.
   */
  public function rejectUserIfTfaBypassed(AccountSetEvent $event): void {
    $account = $event->getAccount();

    // Auth method is exempt from TFA.
    /** @var FALSE|object{'data': mixed} $auth_method_bypass_data */
    $auth_method_bypass_data = $this->memoryCache->get('tfa_auth_method_bypass_approved');
    if ($auth_method_bypass_data !== FALSE) {
      $uid = $auth_method_bypass_data->data;
      if (is_int($uid) && $uid === (int) $account->id()) {
        $this->memoryCache->delete('tfa_auth_method_bypass_approved');
        return;
      }
    }

    // Account Switcher allows a temporary run as another user, as such
    // it is allowed to bypass during switchTo() and switchBack().
    /** @var FALSE|object{'data': mixed} $account_switcher_bypass_data */
    $account_switcher_bypass_data = $this->memoryCache->get('tfa_account_switcher_bypass');
    if ($account_switcher_bypass_data !== FALSE) {
      $uid = $account_switcher_bypass_data->data;
      if (is_int($uid) && $uid === (int) $account->id()) {
        $this->memoryCache->delete('tfa_account_switcher_bypass');
        return;
      }
    }

    // Anonymous users allowed as they pose no additional risk.
    // We check anonymous after the other methods to ensure cache entries are
    // deleted when used.
    if ($account->isAnonymous()) {
      return;
    }

    // Session based authentication validation.
    if ($this->session->has('tfa_complete')) {
      $session_account = $this->session->get('tfa_complete');
      if (is_int($session_account) && $session_account === (int) $account->id()) {
        // Session has completed tfa auth for event user.
        return;
      }
    }

    // Authenticated during this request using user.auth service.
    /** @var FALSE|object{'data': mixed} $tfa_complete_this_request */
    $tfa_complete_this_request = $this->memoryCache->get('tfa_complete');
    if ($tfa_complete_this_request !== FALSE) {
      $user_auth_as_id = $tfa_complete_this_request->data;
      if (is_int($user_auth_as_id) && $user_auth_as_id === (int) $event->getAccount()->id()) {
        return;
      }
    }

    // Check if TFA is required for the user.
    $user_storage = $this->entityTypeManager->getStorage('user');
    $user = $user_storage->load($account->id());
    if ($user) {
      $login_context = $this->loginContextFactory->createContextFromUser($user);

      if ($login_context->isTfaDisabled()) {
        // TFA is disabled.
        $this->memoryCache->set('tfa_complete', (int) $user->id());
        return;
      }

      if (!$login_context->isReady()) {
        // TFA is enabled but not yet configured for the user.
        if ($login_context->canLoginWithoutTfa()) {
          // TFA is not required.
          $login_context->hasSkipped();
          $this->memoryCache->set('tfa_complete', (int) $user->id());
          return;
        }
      }

    }
    // No condition allowed the request. Purge any session and throw an
    // exception.
    $this->session->invalidate();
    throw new TfaAccessDenied('Request does not have a valid TFA verification.');
  }

  /**
   * Converts the exception from rejectUserIfTfaBypassed() to a response.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The event to process.
   */
  public function onException(ExceptionEvent $event): void {
    $exception = $event->getThrowable();
    if ($exception instanceof TfaAccessDenied) {
      $message = $this->t(
        'An unexpected fault occurred with your account authentication. :helpText',
        [':helpText' => $this->configFactory->get('tfa.settings')->get('help_text')]
      );
      $markup = [
        '#cache' => [
          'max-age' => 0,
        ],
        '#markup' => $message,
      ];
      $response = $this->bareRenderer->renderBarePage($markup, 'Unexpected Access Fault', 'maintenance_page');
      $response->setStatusCode(403);
      $event->setResponse($response);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[AccountEvents::SET_USER][] = ['rejectUserIfTfaBypassed', 50];
    $events[KernelEvents::EXCEPTION][] = ['onException', 100];
    return $events;
  }

}
