<?php

namespace Drupal\Tests\upgrade_status\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\upgrade_status\DeprecationMessage;

/**
 * Tests analysing Twig templates.
 *
 * @group upgrade_status
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
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

    $this->assertCount(2, $twig_deprecations, var_export($twig_deprecations, TRUE));
    $this->assertContainsEquals(new DeprecationMessage(
      'Since upgrade_status_test_twig 1.0: Twig Filter "deprecatedfilter" is deprecated. See https://drupal.org/node/3071078.',
      $templates_directory . '/test.html.twig',
      '10',
      'TwigDeprecationAnalyzer'
    ), $twig_deprecations);
    // Use of spaceless leads to syntax error in Drupal 10.
    $this->assertContainsEquals(new DeprecationMessage(
      sprintf('Twig template %s/spaceless.html.twig contains a syntax error and cannot be parsed.', $templates_directory),
      $templates_directory . '/spaceless.html.twig',
      '2',
      'TwigDeprecationAnalyzer'
    ), $twig_deprecations);
  }

}
