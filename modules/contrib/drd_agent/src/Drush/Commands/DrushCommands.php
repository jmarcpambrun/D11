<?php

namespace Drupal\drd_agent\Drush\Commands;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\drd_agent\Setup;
use Drush\Attributes\Argument;
use Drush\Attributes\Command;
use Drush\Attributes\Usage;
use Drush\Commands\DrushCommands as RootDrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class Base.
 *
 * @package Drupal\drd_agent
 */
class DrushCommands extends RootDrushCommands {
  use StringTranslationTrait;

  /**
   * The setup service.
   *
   * @var \Drupal\drd_agent\Setup
   */
  protected Setup $setupService;

  /**
   * Drush constructor.
   *
   * @param \Drupal\drd_agent\Setup $setup_service
   *   The setup service.
   */
  public function __construct(Setup $setup_service) {
    parent::__construct();
    $this->setupService = $setup_service;
  }

  /**
   * Return an instance of these Drush commands.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   *
   * @return \Drupal\drd_agent\Drush\Commands\DrushCommands
   *   The instance of Drush commands.
   */
  public static function create(ContainerInterface $container): DrushCommands {
    return new self(
      $container->get('drd_agent.setup'),
    );
  }

  /**
   * Configure this domain for communication with a DRD instance.
   *
   * @param string $token
   *   The token.
   */
  #[Command(name: 'drd:agent:setup', aliases: ['drd-agent-setup'])]
  #[Argument(name: 'token', description: 'Base64 and json encoded array of all variables required such that DRD can communicate with this domain in the future.')]
  #[Usage(name: 'drd:agent:setup', description: 'Configure this domain for communication with a DRD instance.')]
  public function setup(string $token): void {
    $_SESSION['drd_agent_authorization_values'] = $token;
    $values = $this->setupService->execute();
    unset($_SESSION['drd_agent_authorization_values']);
    if (isset($values['redirect']) && !empty($values['redirect'])) {
      $this->logger()?->success($this->t("To finish configuring the agent, you must access (authenticated) the following URL of the DRD portal where it is being added: \n @url", ['@url' => $values['redirect']]));
    }
    else {
      $this->logger()?->error($this->t("There was an error configuring the DRD agent."));
    }
  }

}
