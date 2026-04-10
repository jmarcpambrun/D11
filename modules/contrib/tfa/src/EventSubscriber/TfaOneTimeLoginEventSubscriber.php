<?php

declare(strict_types=1);

namespace Drupal\tfa\EventSubscriber;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\tfa\TfaLoginContextFactory;
use Drupal\tfa\TfaLoginTrait;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber for enforcing TFA with One Time Login links.
 *
 * @internal
 */
final class TfaOneTimeLoginEventSubscriber implements EventSubscriberInterface {
  use StringTranslationTrait;
  use TfaLoginTrait;

  /**
   * Constructs a new TfaOneTimeLoginEventSubscriber.
   */
  public function __construct(
    protected RouteMatchInterface $routeMatch,
    protected TfaLoginContextFactory $loginContextFactory,
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TimeInterface $time,
    protected LoggerChannelFactoryInterface $loggerChannelFactory,
    protected PrivateTempStoreFactory $tempStoreFactory,
    protected UrlGeneratorInterface $urlGenerator,
    protected MessengerInterface $messenger,
    TranslationInterface $translation,
    #[Autowire(service: 'cache.tfa_memcache')]
    protected CacheBackendInterface $memoryCache,
    protected SessionManagerInterface $sessionManager,
    PrivateKey $private_key,
  ) {
    $this->setStringTranslation($translation);
    $this->setPrivateKey($private_key);
  }

  /**
   * Redirect One Time Logins when users require TFA.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  public function redirectOneTimeLogin(RequestEvent $event): void {

    if ($this->routeMatch->getRouteName() !== 'user.reset.login') {
      return;
    }

    $request = $event->getRequest();
    $uid = (int) $this->routeMatch->getRawParameter('uid');
    $timestamp = (int) $this->routeMatch->getRawParameter('timestamp');
    $hash = $this->routeMatch->getRawParameter('hash');

    if ($hash === NULL) {
      $this->setRedirectToUserPassPage($event);
      return;
    }

    /** @var \Drupal\user\UserInterface|null $user */
    $user = $this->entityTypeManager->getStorage('user')->load($uid);
    if ($user === NULL || !$user->isActive() || !$user->isAuthenticated()) {
      return;
    }
    $login_context = $this->loginContextFactory->createContextFromUser($user);

    if ($login_context->isTfaDisabled()) {
      return;
    }

    if (!$login_context->isReady()) {
      // Any message set by canLoginWithoutTfa() will display on the next
      // page load.
      if ($login_context->canLoginWithoutTfa()) {
        $login_context->hasSkipped();
        $this->memoryCache->set('tfa_complete', $uid);
        return;
      }
      $this->setRedirectToUserPassPage($event);
      return;
    }

    if ($login_context->pluginAllowsLogin()) {
      $this->messenger->addStatus($this->t('You have logged in on a trusted system.'));
      $this->memoryCache->set('tfa_complete', $uid);
      return;
    }

    // The following code is based on core 9.4.6.
    // @see \Drupal\user\Controller\UserController::resetPassLogin()
    $current = $this->time->getRequestTime();

    // Time out, in seconds, until login URL expires.
    $timeout = $this->configFactory->get('user.settings')->get('password_reset_timeout');
    // No time out for first time login.
    if ($user->getLastLoginTime() && $current - $timestamp > $timeout) {
      $this->messenger->addError($this->t('You have tried to use a one-time login link that has expired. Please request a new one using the form below.'));
      $this->setRedirectToUserPassPage($event);
      return;
    }

    if (($timestamp < $user->getLastLoginTime()) || ($timestamp > $current) || !hash_equals($hash, user_pass_rehash($user, $timestamp))) {
      $this->messenger->addError($this->t('You have tried to use a one-time login link that has either been used or is no longer valid. Please request a new one using the form below.'));
      $this->setRedirectToUserPassPage($event);
      return;
    }

    // CWE-384 Session Fixation protection for EntryForm.
    $this->sessionManager->regenerate();

    $token = Crypt::randomBytesBase64(55);
    $request->getSession()->set('pass_reset_' . $uid, $token);

    // Begin TFA and set process context.
    // Log the one-time login link attempts.
    $this->loggerChannelFactory->get('tfa')->notice('User %name used one-time login link at time %timestamp.', [
      '%name' => $user->getDisplayName(),
      '%timestamp' => $current,
    ]);
    // Store UID in order to later verify access to entry form.
    $this->tempStoreFactory->get('tfa')->set('tfa-entry-uid', $user->id());

    $destination = Url::fromRoute(
      'tfa.entry',
      [
        'uid' => $uid,
        'hash' => $this->getLoginHash($user),
      ],
      [
        'query' => ['pass-reset-token' => $token],
      ]
    )
      ->setUrlGenerator($this->urlGenerator)
      ->toString();

    $redirect = new RedirectResponse($destination);
    $event->setResponse($redirect);
  }

  /**
   * Configure the event to redirect to the 'user.pass' page.
   */
  private function setRedirectToUserPassPage(RequestEvent $event): void {
    $destination = Url::fromRoute('user.pass')->setUrlGenerator($this->urlGenerator)->toString();
    $redirect = new RedirectResponse($destination);
    $event->setResponse($redirect);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // This event needs to run right after the route matcher.
    $events[KernelEvents::REQUEST][] = ['redirectOneTimeLogin', 31];
    return $events;
  }

}
