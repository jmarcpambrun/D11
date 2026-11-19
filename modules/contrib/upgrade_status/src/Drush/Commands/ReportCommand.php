<?php

namespace Drupal\upgrade_status\Drush\Commands;

use Drupal\upgrade_status\Command\ReportCommand as BaseReportCommand;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Runs the report command in Drush.
 *
 * Drush only finds commands in this namespace. The command itself is shared
 * with vendor/bin/dr. PHP doesn't inherit attributes, so the AsCommand
 * attribute is repeated here. Keep it the same as in the parent class.
 */
#[AsCommand(
  name: 'upgrade-status:report',
  description: 'Report how ready the site is for the next major Drupal version.',
  aliases: ['us-r'],
)]
final class ReportCommand extends BaseReportCommand {

  use AutowireTrait;

}
