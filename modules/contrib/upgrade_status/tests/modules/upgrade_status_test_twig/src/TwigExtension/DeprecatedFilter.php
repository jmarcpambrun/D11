<?php

namespace Drupal\upgrade_status_test_twig\TwigExtension;

use Twig\Extension\AbstractExtension;
use Twig\DeprecatedCallableInfo;
use Twig\TwigFilter;

/**
 * Deprecated filter.
 */
class DeprecatedFilter extends AbstractExtension {

  /**
   * Get filters.
   */
  public function getFilters(): array {
    return [new TwigFilter('deprecatedfilter', 'strlen', ['deprecation_info' => new DeprecatedCallableInfo('upgrade_status_test_twig', '1.0')])];
  }

}
