<?php

declare(strict_types=1);

namespace Drupal\maestro\Drush\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\maestro\Engine\MaestroOrchestrationRunner;
use Drupal\maestro\Engine\OrchestrationOutcome;
use Drupal\user\UserInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drush commands for the Maestro module.
 */
final class MaestroCommands extends DrushCommands {

  /**
   * Constructs a MaestroCommands object.
   *
   * @param \Drupal\maestro\Engine\MaestroOrchestrationRunner $orchestrationRunner
   *   The orchestration runner.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager, used to load the --user account.
   */
  public function __construct(
    private readonly MaestroOrchestrationRunner $orchestrationRunner,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
  }

  /**
   * Creates the command file from the Drupal container.
   *
   * Uses create() rather than Drush's AutowireTrait, which only exists in
   * Drush 13+, so the commands still load on Drush 12.
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('maestro.orchestration_runner'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Run the Maestro orchestrator (clean the queue).
   */
  #[CLI\Command(name: 'maestro:orchestrate', aliases: ['maestro-orchestrate', 'maestro-orch'])]
  #[CLI\Option(name: 'user', description: 'Run the orchestrator as this user (username or user ID). Overrides the run-as user in the Maestro engine settings. Tasks that check permissions, such as Tool tasks, are checked against this account.')]
  #[CLI\Usage(name: 'drush maestro:orchestrate', description: 'Run the Maestro orchestrator as the configured run-as user, or anonymous when none is configured.')]
  #[CLI\Usage(name: 'drush maestro:orchestrate --user=USERNAME', description: 'Run the orchestrator as an existing user, given by username or user ID.')]
  public function orchestrate(array $options = ['user' => self::OPT]): int {
    $account = NULL;
    $user_option = $options['user'] ?? NULL;
    if ($user_option !== NULL && $user_option !== '' && $user_option !== TRUE) {
      $storage = $this->entityTypeManager->getStorage('user');
      $account = ctype_digit((string) $user_option)
        ? $storage->load($user_option)
        : (current($storage->loadByProperties(['name' => $user_option])) ?: NULL);
      if (!$account instanceof UserInterface) {
        $this->logger()->error(dt('User "@user" does not exist. The orchestrator was not run.', ['@user' => $user_option]));
        return self::EXIT_FAILURE;
      }
      if ($account->isBlocked()) {
        $this->logger()->error(dt('User "@user" is blocked. The orchestrator was not run.', ['@user' => $account->getAccountName()]));
        return self::EXIT_FAILURE;
      }
      $this->logger()->notice(dt('Running as user "@user" (uid @uid).', ['@user' => $account->getAccountName(), '@uid' => $account->id()]));
    }
    return $this->runOrchestrator($account);
  }

  /**
   * Runs the orchestrator and reports the outcome.
   *
   * @param \Drupal\user\UserInterface|null $account
   *   The account to run as, or NULL for the configured run-as user.
   *
   * @return int
   *   The Drush exit code.
   */
  protected function runOrchestrator(?UserInterface $account = NULL): int {
    $this->logger()->notice('Starting Maestro orchestrator...');
    return match ($this->orchestrationRunner->run($account)) {
      OrchestrationOutcome::Completed => $this->succeed('Maestro orchestrator finished.'),
      OrchestrationOutcome::Locked => $this->fail('Could not acquire maestro_orchestrator lock. Another process may be running.', 'warning'),
      OrchestrationOutcome::AccountUnavailable => $this->fail('The run-as user does not exist or is blocked. The orchestrator was not run. See the Maestro log for details.', 'error'),
    };
  }

  /**
   * Start a Maestro process by template machine name.
   */
  #[CLI\Command(name: 'maestro:start-process', aliases: ['maestro-start-process', 'maestro-start'])]
  #[CLI\Argument(name: 'template_machine_name', description: 'The machine name of the Maestro template to start.')]
  #[CLI\Usage(name: 'drush maestro:start-process my_template', description: "Start a Maestro process using the 'my_template' template.")]
  public function startProcess(string $template_machine_name): int {
    $template = MaestroEngine::getTemplate($template_machine_name);
    if (!$template) {
      $this->logger()->error('Error!  No template by that name exists!');
      return self::EXIT_FAILURE;
    }
    $engine = new MaestroEngine();
    $pid = $engine->newProcess($template_machine_name);
    if (!$pid) {
      $this->logger()->error('Error!  Process unable to start!');
      return self::EXIT_FAILURE;
    }
    $this->logger()->success('Process Started');
    // Run the orchestrator for us once on process kickoff.
    return $this->runOrchestrator();
  }

  /**
   * Logs a success message and returns the success exit code.
   */
  private function succeed(string $message): int {
    $this->logger()->success($message);
    return self::EXIT_SUCCESS;
  }

  /**
   * Logs a message at the given level and returns the failure exit code.
   */
  private function fail(string $message, string $level): int {
    $this->logger()->log($level, $message);
    return self::EXIT_FAILURE;
  }

}
