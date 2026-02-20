<?php

namespace Drupal\openai\Http;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\key\KeyRepositoryInterface;
use OpenAI\Client;

/**
 * Service for generating OpenAI clients.
 */
class ClientFactory {

  /**
   * The config settings object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The key repository.
   * @var \Drupal\key\KeyRepositoryInterface
   */
 
  protected KeyRepositoryInterface $keyRepository;


  /**
   * Constructs a new ClientFactory instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\key\KeyRepositoryInterface $keyRepository
   *   The key repository.

   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    ModuleHandlerInterface $module_handler,
     KeyRepositoryInterface $keyRepository,
  ) {
    $this->config = $config_factory->get('openai.settings');
    $this->moduleHandler = $module_handler;
    $this->keyRepository = $keyRepository;

  }

  /**
   * Creates a new OpenAI client instance.
   *
   * @return \OpenAI\Client
   *   The client instance.
   */
  public function create(): Client {
    $api_key = $this->config->get('api_key');
    if ($this->moduleHandler->moduleExists('key')) {
      $api_key = $this->keyRepository->getKey($api_key)->getKeyValue();
    }
    return \OpenAI::client($api_key, $this->config->get('api_org'));
  }

}
