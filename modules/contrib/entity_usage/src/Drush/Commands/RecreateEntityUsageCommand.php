<?php

declare(strict_types=1);

namespace Drupal\entity_usage\Drush\Commands;

use Drupal\entity_usage\EntityUsageBatchManager;
use Drush\Attributes\Bootstrap;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Entity Usage drush commands.
 */
#[AsCommand(
  name: self::NAME,
  description: 'Recreate all entity usage statistics.',
  aliases: ['eu-r', 'entity-usage-recreate'],
)]
#[Bootstrap(DrupalBootLevels::FULL)]
class RecreateEntityUsageCommand extends Command {
  use AutowireTrait;

  public const string NAME = 'entity-usage:recreate';

  /**
   * {@inheritdoc}
   */
  public function __construct(protected EntityUsageBatchManager $batchManager) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this
      ->addUsage(self::NAME)
      ->addOption('keep-existing-records', description: 'When --keep-existing-records is used, existing entity usage records won\'t be deleted.');
  }

  /**
   * Recreate all entity usage statistics.
   */
  public function execute(InputInterface $input, OutputInterface $output): int {
    $this->batchManager->recreate((bool) $input->getOption('keep-existing-records'));
    drush_backend_batch_process();
    return Command::SUCCESS;
  }

}
