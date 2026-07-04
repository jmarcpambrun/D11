<?php

namespace Drupal\Tests\upgrade_status\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests analysing sample projects.
 *
 * @group upgrade_status
 */
#[RunTestsInSeparateProcesses]
#[Group('upgrade_status')]
class UpgradeStatusAnalyzeTest extends UpgradeStatusTestBase {

  /**
   * Test analyzer.
   */
  public function testAnalyzer() {
    $this->drupalLogin($this->drupalCreateUser(['administer software updates']));
    $this->runFullScan();

    /** @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface $key_value */
    $key_value = \Drupal::service('keyvalue')->get('upgrade_status_scan_results');

    // Check if the project has scan result in the keyValueStorage.
    $this->assertTrue($key_value->has('upgrade_status_test_error'));
    $this->assertTrue($key_value->has('upgrade_status_test_fatal'));
    $this->assertTrue($key_value->has('upgrade_status_test_11_compatible'));
    $this->assertTrue($key_value->has('upgrade_status_test_12_compatible'));
    $this->assertTrue($key_value->has('upgrade_status_test_13_compatible'));
    $this->assertTrue($key_value->has('upgrade_status_test_submodules'));
    $this->assertTrue($key_value->has('upgrade_status_test_submodules_with_error'));
    $this->assertTrue($key_value->has('upgrade_status_test_contrib_error'));
    $this->assertTrue($key_value->has('upgrade_status_test_contrib_12_compatible'));
    $this->assertTrue($key_value->has('upgrade_status_test_twig'));
    $this->assertTrue($key_value->has('upgrade_status_test_theme'));
    $this->assertTrue($key_value->has('upgrade_status_test_library'));
    $this->assertTrue($key_value->has('upgrade_status_test_deprecated'));

    // The project upgrade_status_test_submodules_a shouldn't have scan result,
    // because it's a submodule of 'upgrade_status_test_submodules',
    // and we always want to run the scan on root modules.
    $this->assertFalse($key_value->has('upgrade_status_test_submodules_a'));

    $report = $key_value->get('upgrade_status_test_error');
    $this->assertNotEmpty($report);
    $this->assertEquals(7, $report['data']['totals']['file_errors']);
    $this->assertCount(7, $report['data']['files']);
    $file = reset($report['data']['files']);
    $this->assertEquals('UpgradeStatusTestErrorController.php', basename(key($report['data']['files'])));
    $message = $file['messages'][0];
    $this->assertEquals("Call to deprecated function upgrade_status_test_contrib_error_function_9_to_10(). Deprecated in drupal:9.1.0 and is removed from drupal:10.0.0. Use the replacement instead.", $message['message']);
    $this->assertEquals(16, $message['line']);
    $file = next($report['data']['files']);
    $this->assertEquals('ExtendingClass.php', basename(key($report['data']['files'])));
    $message = $file['messages'][0];
    $this->assertEquals("Class Drupal\upgrade_status_test_error\ExtendingClass extends deprecated class Drupal\upgrade_status_test_error\DeprecatedBaseClass. Deprecated in drupal:9.1.0 and is removed from drupal:10.0.0. Instead, use so and so. See https://www.drupal.org/project/upgrade_status.", $message['message']);
    $this->assertEquals(10, $message['line']);
    $file = next($report['data']['files']);
    $this->assertEquals('UpgradeStatusTestErrorEntity.php', basename(key($report['data']['files'])));
    $message = $file['messages'][0];
    $this->assertEquals("Configuration entity must define a `config_export` key. See https://www.drupal.org/node/2481909", $message['message']);
    $this->assertEquals(15, $message['line']);
    $file = next($report['data']['files']);
    $this->assertEquals('upgrade_status_test_error.routing.yml', basename(key($report['data']['files'])));
    $message = $file['messages'][0];
    $this->assertEquals("The _access_node_revision routing requirement is deprecated in drupal:9.3.0 and is removed from drupal:10.0.0. Use _entity_access instead. See https://www.drupal.org/node/3161210.", $message['message']);
    $this->assertEquals(0, $message['line']);
    $file = next($report['data']['files']);
    $this->assertEquals('upgrade_status_test_error.css', basename(key($report['data']['files'])));
    $message = $file['messages'][0];
    $this->assertEquals("The #drupal-off-canvas selector is deprecated in drupal:9.5.0 and is removed from drupal:10.0.0. See https://www.drupal.org/node/3305664.", $message['message']);
    $this->assertEquals(0, $message['line']);
    $file = next($report['data']['files']);
    $this->assertEquals('views.view.remove_default_argument_skip_url.yml', basename(key($report['data']['files'])));
    $message = $file['messages'][0];
    $this->assertEquals("Support from all Views contextual filter settings for the default_argument_skip_url setting is removed from drupal:11.0.0. No replacement is provided. See https://www.drupal.org/node/3382316.", $message['message']);
    $this->assertEquals(109, $message['line']);
    $file = next($report['data']['files']);
    $this->assertEquals('upgrade_status_test_error.info.yml', basename(key($report['data']['files'])));
    $message = $file['messages'][0];
    $this->assertEquals("Add core_version_requirement to designate which Drupal versions is the extension compatible with. See https://drupal.org/node/3070687.", $message['message']);
    $this->assertEquals(1, $message['line']);

    $report = $key_value->get('upgrade_status_test_fatal');
    $this->assertNotEmpty($report);
    $this->assertEquals(2, $report['data']['totals']['file_errors']);
    $this->assertCount(2, $report['data']['files']);
    $file = reset($report['data']['files']);
    $message = $file['messages'][0];
    $this->assertEquals('fatal.php', basename(key($report['data']['files'])));
    $this->assertEquals("Syntax error, unexpected T_STRING on line 5", $message['message']);
    $this->assertEquals(5, $message['line']);
    $file = next($report['data']['files']);
    $this->assertEquals('upgrade_status_test_fatal.info.yml', basename(key($report['data']['files'])));
    $message = $file['messages'][0];
    $this->assertEquals("Add core_version_requirement to designate which Drupal versions is the extension compatible with. See https://drupal.org/node/3070687.", $message['message']);
    $this->assertEquals(1, $message['line']);

    // The Drupal 10, 11, 12 compatible test modules are not Drupal 13
    // compatible.
    $test_compatibles = [
      'upgrade_status_test_12_compatible' => ['^10 || ^11 || ^12', 5],
      'upgrade_status_test_contrib_12_compatible' => ['^10.1 || ^11 || ^12', 7],
    ];
    foreach ($test_compatibles as $name => $condition) {
      $report = $key_value->get($name);
      $this->assertNotEmpty($report);
      if ($this->getDrupalCoreMajorVersion() < 12) {
        $this->assertEquals(0, $report['data']['totals']['file_errors']);
        $this->assertCount(0, $report['data']['files']);
      }
      else {
        $this->assertEquals(1, $report['data']['totals']['file_errors']);
        $this->assertCount(1, $report['data']['files']);
        $file = reset($report['data']['files']);
        $this->assertEquals($name . '.info.yml', basename(key($report['data']['files'])));
        $message = $file['messages'][0];
        $this->assertEquals("Value of core_version_requirement: $condition[0] is not compatible with the next major version of Drupal core. See https://drupal.org/node/3070687.", $message['message']);
        $this->assertEquals($condition[1], $message['line']);
      }
    }

    // The Drupal 12 compatible test module is also Drupal 10 and 11 compatible.
    $report = $key_value->get('upgrade_status_test_12_compatible');
    $this->assertNotEmpty($report);
    $this->assertEquals($this->getDrupalCoreMajorVersion() < 12 ? 0 : 1, $report['data']['totals']['file_errors']);
    $this->assertCount($this->getDrupalCoreMajorVersion() < 12 ? 0 : 1, $report['data']['files']);

    // The Drupal 13 compatible test module is also Drupal 11 and 12 compatible.
    $report = $key_value->get('upgrade_status_test_13_compatible');
    $this->assertNotEmpty($report);
    $this->assertEquals(0, $report['data']['totals']['file_errors']);
    $this->assertCount(0, $report['data']['files']);

    $report = $key_value->get('upgrade_status_test_contrib_error');
    $this->assertNotEmpty($report);
    $this->assertEquals(6, $report['data']['totals']['file_errors']);
    $this->assertCount(2, $report['data']['files']);
    $file = reset($report['data']['files']);
    $this->assertEquals('UpgradeStatusTestContribErrorController.php', basename(key($report['data']['files'])));
    $message = $file['messages'][0];
    $this->assertEquals("Call to deprecated function upgrade_status_test_contrib_error_function_9_to_10(). Deprecated in drupal:9.1.0 and is removed from drupal:10.0.0. Use the replacement instead.", $message['message']);
    $this->assertEquals(16, $message['line']);
    $this->assertEquals('old', $message['upgrade_status_category']);
    $message = $file['messages'][1];
    $this->assertEquals("Call to deprecated function upgrade_status_test_contrib_error_function_9_to_11(). Deprecated in drupal:9.1.0 and is removed from drupal:11.0.0. Use the replacement instead.", $message['message']);
    $this->assertEquals(17, $message['line']);
    $this->assertEquals('old', $message['upgrade_status_category']);
    $message = $file['messages'][2];
    $this->assertEquals("Call to deprecated function upgrade_status_test_contrib_error_function_10_to_11(). Deprecated in drupal:10.6.0 and is removed from drupal:11.0.0. Use the replacement instead.", $message['message']);
    $this->assertEquals(18, $message['line']);
    $this->assertEquals($this->getDrupalCoreMajorVersion() < 11 ? 'later' : 'old', $message['upgrade_status_category']);
    $message = $file['messages'][3];
    $this->assertEquals("Call to deprecated function upgrade_status_test_contrib_error_function_10_to_12(). Deprecated in drupal:10.0.0 and is removed from drupal:12.0.0. Use the replacement instead.", $message['message']);
    $this->assertEquals(19, $message['line']);
    $this->assertEquals($this->getDrupalCoreMajorVersion() < 11 ? 'ignore' : 'old', $message['upgrade_status_category']);
    $message = $file['messages'][4];
    $this->assertEquals("Call to deprecated function upgrade_status_test_contrib_error_function_11_to_13(). Deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. Use the replacement instead.", $message['message']);
    $this->assertEquals(20, $message['line']);
    $this->assertEquals($this->getDrupalCoreMajorVersion() < 12 ? 'ignore' : 'old', $message['upgrade_status_category']);
    $file = next($report['data']['files']);
    $this->assertEquals('upgrade_status_test_contrib_error.info.yml', basename(key($report['data']['files'])));
    $message = $file['messages'][0];
    $this->assertEquals("Add core_version_requirement to designate which Drupal versions is the extension compatible with. See https://drupal.org/node/3070687.", $message['message']);
    $this->assertEquals(1, $message['line']);
    $this->assertEquals('uncategorized', $message['upgrade_status_category']);

    $report = $key_value->get('upgrade_status_test_twig');
    $this->assertNotEmpty($report);
    $this->assertEquals(5, $report['data']['totals']['file_errors']);
    $this->assertCount(3, $report['data']['files']);

    $file = array_shift($report['data']['files']);
    $upgrade_status_test_twig_directory = $this->container->get('module_handler')->getModule('upgrade_status_test_twig')->getPath();
    $this->assertEquals(sprintf('Twig template %s/templates/spaceless.html.twig contains a syntax error and cannot be parsed.', $upgrade_status_test_twig_directory), $file['messages'][0]['message']);
    $file = array_shift($report['data']['files']);
    $this->assertEquals('Since upgrade_status_test_twig 1.0: Twig Filter "deprecatedfilter" is deprecated. See https://drupal.org/node/3071078.', $file['messages'][0]['message']);
    $this->assertEquals(10, $file['messages'][0]['line']);
    $this->assertEquals('Template is attaching a deprecated library. The "upgrade_status_test_library/deprecated_library" asset library is deprecated for testing.', $file['messages'][1]['message']);
    $this->assertEquals(1, $file['messages'][1]['line']);
    $this->assertEquals('Template is attaching a deprecated library. The "upgrade_status_test_twig/deprecated_library" asset library is deprecated for testing.', $file['messages'][2]['message']);
    $this->assertEquals(2, $file['messages'][2]['line']);

    $report = $key_value->get('upgrade_status_test_theme');
    $this->assertNotEmpty($report);
    $this->assertEquals($this->getDrupalCoreMajorVersion() < 12 ? 4 : 5, $report['data']['totals']['file_errors']);
    $this->assertCount($this->getDrupalCoreMajorVersion() < 12 ? 2 : 3, $report['data']['files']);
    $file = reset($report['data']['files']);
    foreach ([0 => 2, 1 => 4] as $index => $line) {
      $message = $file['messages'][$index];
      $this->assertEquals('Since upgrade_status_test_twig 1.0: Twig Filter "deprecatedfilter" is deprecated. See https://drupal.org/node/3071078.', $message['message']);
      $this->assertEquals($line, $message['line']);
    }
    $file = next($report['data']['files']);
    $this->assertEquals('Theme is overriding a deprecated library. The "upgrade_status_test_library/deprecated_library" asset library is deprecated for testing.', $file['messages'][0]['message']);
    $this->assertEquals(0, $file['messages'][0]['line']);
    $this->assertEquals('Theme is extending a deprecated library. The "upgrade_status_test_twig/deprecated_library" asset library is deprecated for testing.', $file['messages'][1]['message']);
    $this->assertEquals(0, $file['messages'][1]['line']);
    if ($this->getDrupalCoreMajorVersion() > 11) {
      // In Drupal 12, this theme is not yet forward compatible.
      $file = next($report['data']['files']);
      $this->assertEquals("Value of core_version_requirement: ^9 || ^10 || ^11 || ^12 is not compatible with the next major version of Drupal core. See https://drupal.org/node/3070687.", $file['messages'][0]['message']);
      $this->assertEquals(5, $file['messages'][0]['line']);
    }

    $report = $key_value->get('upgrade_status_test_library');
    $this->assertNotEmpty($report);
    $this->assertEquals(6, $report['data']['totals']['file_errors']);
    $this->assertCount(3, $report['data']['files']);
    $file = reset($report['data']['files']);
    $this->assertEquals("The 'library' library is depending on a deprecated library. The \"upgrade_status_test_library/deprecated_library\" asset library is deprecated for testing.", $file['messages'][0]['message']);
    $this->assertEquals(0, $file['messages'][0]['line']);
    $this->assertEquals("The 'library' library is depending on a deprecated library. The \"upgrade_status_test_twig/deprecated_library\" asset library is deprecated for testing.", $file['messages'][1]['message']);
    $this->assertEquals(0, $file['messages'][1]['line']);
    $this->assertEquals("The invalid_library_without_extension library does not have an extension name and is therefore not valid.", $file['messages'][2]['message']);
    $this->assertEquals(0, $file['messages'][2]['line']);
    $file = $report['data']['files'][array_keys($report['data']['files'])[1]];
    $this->assertEquals('The referenced library is deprecated. The "upgrade_status_test_library/deprecated_library" asset library is deprecated for testing.', $file['messages'][0]['message']);
    $this->assertEquals(13, $file['messages'][0]['line']);
    $this->assertEquals('The referenced library is deprecated. The "upgrade_status_test_twig/deprecated_library" asset library is deprecated for testing.', $file['messages'][1]['message']);
    $this->assertEquals(15, $file['messages'][1]['line']);

    $report = $key_value->get('upgrade_status_test_library_exception');
    $this->assertNotEmpty($report);
    $this->assertEquals(2, $report['data']['totals']['file_errors']);
    $this->assertCount(2, $report['data']['files']);
    $file = reset($report['data']['files']);
    $this->assertEquals("Incomplete library definition for definition 'library_exception' in extension 'upgrade_status_test_library_exception'", $file['messages'][0]['message']);

    // Module upgrade_status_test_submodules_with_error_a shouldn't have scan
    // result, but its info.yml errors should appear in its parent scan.
    $this->assertFalse($key_value->has('upgrade_status_test_submodules_with_error_a'));
    $report = $key_value->get('upgrade_status_test_submodules_with_error');
    $this->assertNotEmpty($report);
    $this->assertEquals(2, $report['data']['totals']['file_errors']);
    $this->assertCount(2, $report['data']['files']);

    $report = $key_value->get('upgrade_status_test_deprecated');
    $this->assertNotEmpty($report);
    $this->assertEquals(2, $report['data']['totals']['file_errors']);
    $this->assertCount(1, $report['data']['files']);
    $file = reset($report['data']['files']);
    // Check on index 1, as index 0 will be the info file error, which is
    // checked before.
    $this->assertEquals("This extension is deprecated. Don't use it. See https://drupal.org/project/upgrade_status.", $file['messages'][1]['message']);
    $this->assertEquals(5, $file['messages'][1]['line']);
  }

}
