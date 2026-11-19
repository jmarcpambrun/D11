<?php

namespace Drupal\upgrade_status\Drush\Commands;

use Drupal\upgrade_status\Command\AnalyzeCommand as BaseAnalyzeCommand;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Runs the analyze command in Drush.
 *
 * Drush only finds commands in this namespace. The command itself is shared
 * with vendor/bin/dr. PHP doesn't inherit attributes, so the AsCommand
 * attribute is repeated here. Keep it the same as in the parent class, except
 * for the upgrade_status:analyze alias. That was the name of the command in
 * older versions of this module, so Drush scripts may still use it.
 */
#[AsCommand(
  name: 'upgrade-status:analyze',
  description: 'Analyze projects and output results in selected format.',
  aliases: ['upgrade_status:analyze', 'us-a'],
)]
final class AnalyzeCommand extends BaseAnalyzeCommand {

  use AutowireTrait;

  /**
   * {@inheritdoc}
   */
  protected function initialize(InputInterface $input, OutputInterface $output): void {
    if ($input->getFirstArgument() === 'upgrade_status:analyze') {
      $this->getErrorOutput($output)->writeln('<comment>The upgrade_status:analyze command name is deprecated and will be removed from the next major version of Upgrade Status. Use upgrade-status:analyze instead.</comment>');
    }
  }

}
