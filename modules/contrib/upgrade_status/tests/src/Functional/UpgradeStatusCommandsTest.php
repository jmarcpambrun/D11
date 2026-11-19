<?php

namespace Drupal\Tests\upgrade_status\Functional;

use Drupal\upgrade_status\Command\AnalyzeCommand;
use Drupal\upgrade_status\Command\ReportCommand;
use Drupal\upgrade_status\Drush\Commands\AnalyzeCommand as DrushAnalyzeCommand;
use Drupal\upgrade_status\Drush\Commands\ReportCommand as DrushReportCommand;
use Drush\TestTraits\DrushTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Tests the commands in Drush.
 *
 * @coversDefaultClass \Drupal\upgrade_status\Command\AnalyzeCommand
 *
 * @group upgrade_status
 */
#[RunTestsInSeparateProcesses]
#[Group('upgrade_status')]
class UpgradeStatusCommandsTest extends UpgradeStatusCommandsTestBase {

  use DrushTestTrait;

  /**
   * Tests the Drush command.
   */
  public function testDrushCommand() {
    // The Drush command repeats the attribute, because PHP doesn't inherit it.
    // It only adds the old name of the command as an alias.
    $attribute = (new \ReflectionClass(AnalyzeCommand::class))->getAttributes(AsCommand::class)[0]->getArguments();
    $attribute['aliases'] = ['upgrade_status:analyze', ...$attribute['aliases']];
    $this->assertEquals($attribute, (new \ReflectionClass(DrushAnalyzeCommand::class))->getAttributes(AsCommand::class)[0]->getArguments());

    $this->assertCommand(function (string $name, array $projects, array $options, int $expected_code): string {
      $this->drush($name, $projects, $options, NULL, NULL, $expected_code);
      // Only the old name of the command shows a deprecation message.
      $deprecation = 'The upgrade_status:analyze command name is deprecated';
      if ($name === 'upgrade_status:analyze') {
        $this->assertStringContainsString($deprecation, $this->getErrorOutput());
      }
      else {
        $this->assertStringNotContainsString($deprecation, $this->getErrorOutput());
      }
      return $this->getOutput();
    });

    $this->assertEquals(
      (new \ReflectionClass(ReportCommand::class))->getAttributes(AsCommand::class)[0]->getArguments(),
      (new \ReflectionClass(DrushReportCommand::class))->getAttributes(AsCommand::class)[0]->getArguments(),
    );
    $this->assertReport(function (array $options): string {
      $this->drush('us-r', [], $options);
      return $this->getOutput();
    });

    // Unknown groups are an error. The groups have names for the command
    // line, not the internal names of the next steps, like "relax". A wide
    // terminal keeps the error message on one line.
    $this->drush('upgrade-status:report', [], ['groups' => 'environment,relax'], NULL, NULL, 1, NULL, ['COLUMNS' => 500]);
    $this->assertStringContainsString('Unknown groups: relax. Use one or more of these: environment, remove, update, scan, collaborate, fix-with-rector, fix-manually, update-requirements, compatible.', $this->getErrorOutput());

    // A newer release of PHPStan from the update check is in the report
    // accuracy section of the report. CSV has no such section, so it is on the
    // error output.
    if ($release = $this->addPhpStanRelease()) {
      $this->drush('us-r', [], ['groups' => 'environment']);
      $this->assertStringContainsString("- Newer analysis tools are available.", $this->getOutput());
      $this->assertMatchesRegularExpression('/  - PHPStan \\S+ → ' . preg_quote($release, '/') . ' \\(\\[Release notes\\]/', $this->getOutput());
      $this->assertStringContainsString('`composer update phpstan/phpstan --with-dependencies`', $this->getOutput());
      $this->assertStringNotContainsString('Newer versions', $this->getErrorOutput());
      $this->drush('us-r', [], ['groups' => 'environment', 'format' => 'csv']);
      $this->assertStringContainsString("phpstan/phpstan $release", $this->getErrorOutput());
      $this->assertStringNotContainsString('Newer versions', $this->getOutput());
    }
  }

}
