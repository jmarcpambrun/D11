<?php

namespace Drupal\Tests\upgrade_status\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\KernelTests\KernelTestBase;
use Drupal\upgrade_status\DeprecationMessage;

/**
 * Tests analysing Twig templates.
 *
 * @group upgrade_status
 */
#[RunTestsInSeparateProcesses]
#[Group('upgrade_status')]
final class TwigDeprecationAnalyzerTest extends KernelTestBase {

  /**
   * Modules to test.
   *
   * @var array
   */
  protected static $modules = [
    'upgrade_status',
    'upgrade_status_test_twig',
  ];

  /**
   * Test deprecation report.
   */
  public function testDeprecationReport() {
    $extension = $this->container->get('module_handler')->getModule('upgrade_status_test_twig');
    $templates_directory = $extension->getPath() . '/templates';

    $sut = $this->container->get('upgrade_status.twig_deprecation_analyzer');
    $twig_deprecations = $sut->analyze($extension);

    $this->assertCount(4, $twig_deprecations, var_export($twig_deprecations, TRUE));
    $this->assertContainsEquals(new DeprecationMessage(
      'Since upgrade_status_test_twig 1.0: Twig Filter "deprecatedfilter" is deprecated. See https://drupal.org/node/3071078.',
      $templates_directory . '/test.html.twig',
      '10',
      'TwigDeprecationAnalyzer'
    ), $twig_deprecations);
    // Use of spaceless leads to syntax error in Drupal 10.
    $this->assertContainsEquals(new DeprecationMessage(
      sprintf('Twig template %s/spaceless.html.twig contains a syntax error and cannot be parsed: Unknown "spaceless" tag.', $templates_directory),
      $templates_directory . '/spaceless.html.twig',
      '2',
      'TwigDeprecationAnalyzer'
    ), $twig_deprecations);
    // A filter from a module that is not installed is named in the message.
    $this->assertContainsEquals(new DeprecationMessage(
      sprintf('Twig template %s/unknown_filter.html.twig contains a syntax error and cannot be parsed: Unknown "upgrade_status_test_unknown_filter" filter.', $templates_directory),
      $templates_directory . '/unknown_filter.html.twig',
      '2',
      'TwigDeprecationAnalyzer'
    ), $twig_deprecations);
    // Other errors while parsing, like from a broken Twig extension, are
    // reported for the template, and the other templates are still checked.
    $this->assertContainsEquals(new DeprecationMessage(
      sprintf('Twig template %s/broken_tag.html.twig cannot be parsed: LogicException: The upgrade_status_test_broken tag is broken.', $templates_directory),
      $templates_directory . '/broken_tag.html.twig',
      0,
      'TwigDeprecationAnalyzer'
    ), $twig_deprecations);
  }

}
