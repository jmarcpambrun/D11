<?php

declare(strict_types=1);

namespace Drupal\tfa;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;

/**
 * TFA login context factory.
 *
 * @internal
 */
class TfaLoginContextFactory {

  /**
   * Constructs a new TfaUserContextFactory.
   */
  final public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly UserDataInterface $userData,
    private readonly TfaPluginManager $tfaPluginManager,
    private readonly TranslationInterface $translation,
    private readonly MessengerInterface $messenger,
    private readonly LoggerChannelInterface $loggerChannel,
    private readonly UrlGeneratorInterface $urlGenerator,
  ) {
  }

  /**
   * Gets a context for a user.
   */
  public function createContextFromUser(UserInterface $user): TfaLoginContext {
    return new TfaLoginContext(
      $user,
      $this->tfaPluginManager,
      $this->configFactory,
      $this->userData,
      $this->translation,
      $this->messenger,
      $this->loggerChannel,
      $this->urlGenerator,
    );
  }

}
