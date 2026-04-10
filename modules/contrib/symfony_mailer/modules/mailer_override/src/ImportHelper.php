<?php

declare(strict_types=1);

namespace Drupal\mailer_override;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\symfony_mailer\Address;

/**
 * Provides the import helper service.
 */
class ImportHelper implements ImportHelperInterface {

  /**
   * Regular expression for parsing addresses.
   *
   * Matches a string like 'Name <email@address.com>' Anything between the
   * first < and last > counts as the email address. This does not try to cover
   * all edge cases for address.
   */
  protected const FROM_STRING_PATTERN = '~(?<displayName>[^<]*)<(?<addrSpec>.*)>[^>]*~';

  /**
   * Constructs the ImportHelper object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   *
   * @internal
   */
  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function parseAddress(string $encoded, string $langcode = '', ?AccountInterface $account = NULL): array {
    // Split values by comma, but ignore commas encapsulated in double
    // quotes.
    $value = str_getcsv($encoded, escape: '\\');
    foreach ($value as $part) {

      if (empty(trim($part))) {
        continue;
      }
      // Code copied from \Symfony\Component\Mime\Address::create().
      if (strpos($part, '<')) {
        if (!preg_match(self::FROM_STRING_PATTERN, $part, $matches)) {
          throw new \InvalidArgumentException("Could not parse $part as an address.");
        }
        $addresses[] = new Address($matches['addrSpec'], trim($matches['displayName'], ' \'"'), $langcode, $account);
      }
      else {
        $addresses[] = new Address($part, '', $langcode, $account);
      }
    }
    return $addresses ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function policyFromAddresses(array $addresses): array {
    $site_mail = $this->configFactory->get('system.site')->get('mail');

    foreach ($addresses as $address) {
      $value = $address->getEmail();
      $display = '';
      if ($value == $site_mail) {
        $value = '<site>';
      }
      elseif ($user = $address->getAccount()) {
        $value = $user->id();
      }
      else {
        $display = $address->getDisplayName();
      }

      $config['addresses'][] = [
        'value' => $value,
        'display' => $display,
      ];
    }

    return $config ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function policyFromPlainBody(string $body): array {
    return [
      'content' => [
        'value' => $body,
        'format' => 'plain_text',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function config(): ConfigFactoryInterface {
    return $this->configFactory;
  }

}
