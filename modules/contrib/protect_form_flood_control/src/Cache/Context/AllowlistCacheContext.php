<?php

namespace Drupal\protect_form_flood_control\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextInterface;
use Drupal\Core\Cache\Context\RequestStackCacheContextBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Path\PathMatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines a cache context for the client IP's allowlist status.
 *
 * Unlike the core 'ip' context, which varies by every distinct client IP
 * address, this context only ever has two possible values, so it does not
 * degrade dynamic page cache effectiveness the way 'ip' does.
 *
 * Cache context ID: 'protect_form_flood_control_allowlist'.
 */
class AllowlistCacheContext extends RequestStackCacheContextBase implements CacheContextInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The path matcher.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  protected $pathMatcher;

  /**
   * Constructs a new AllowlistCacheContext class.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Path\PathMatcherInterface $path_matcher
   *   The path matcher.
   */
  public function __construct(RequestStack $request_stack, ConfigFactoryInterface $config_factory, PathMatcherInterface $path_matcher) {
    parent::__construct($request_stack);
    $this->configFactory = $config_factory;
    $this->pathMatcher = $path_matcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t('Protect form flood control allowlist status');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    $allowlist = $this->configFactory->get('protect_form_flood_control.settings')->get('general.allowlist') ?: [];
    $patterns = implode("\r\n", $allowlist);
    if (empty($patterns)) {
      return 'not-allowlisted';
    }
    $client_ip = $this->requestStack->getCurrentRequest()->getClientIp();
    return $this->pathMatcher->matchPath($client_ip, $patterns) ? 'allowlisted' : 'not-allowlisted';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata() {
    return new CacheableMetadata();
  }

}
