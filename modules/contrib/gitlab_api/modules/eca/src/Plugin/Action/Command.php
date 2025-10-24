<?php

namespace Drupal\eca_gitlab_api\Plugin\Action;

use Drupal\Component\Plugin\Definition\PluginDefinitionInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca\Plugin\ECA\PluginFormTrait;
use Drupal\eca\Service\YamlParser;
use Drupal\gitlab_api\Api;
use Gitlab\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Wrapper action for all gitlab_api commands.
 *
 * @Action(
 *   id = "eca_gitlab_api_command",
 *   deriver = "\Drupal\eca_gitlab_api\Plugin\Action\CommandDeriver",
 *   eca_version_introduced = "2.3.0"
 * )
 */
class Command extends ConfigurableActionBase {

  use PluginFormTrait;

  /**
   * The GitLab API service.
   *
   * @var \Drupal\gitlab_api\Api
   */
  protected Api $api;

  /**
   * The YAML parser.
   *
   * @var \Drupal\eca\Service\YamlParser
   */
  protected YamlParser $yamlParser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->api = $container->get('gitlab_api.api');
    $instance->yamlParser = $container->get('eca.service.yaml_parser');
    return $instance;
  }

  /**
   * Helper function to receive the params from the plugin definition.
   *
   * @param string $key
   *   The definition key to receive.
   *
   * @return string|array
   *   The params as an array.
   */
  protected function getDefinition(string $key): string|array {
    $definition = $this->getPluginDefinition();
    if ($definition instanceof PluginDefinitionInterface) {
      return [];
    }
    return $definition[$key];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    $config = parent::defaultConfiguration();
    $config['gitlab'] = '';
    $config['token_name'] = '';
    $params = $this->getDefinition('params');
    if (is_array($params)) {
      foreach ($params as $param) {
        $config['api_' . $param['name']] = $param['default'];
      }
    }
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $instances = [];
    foreach ($this->entityTypeManager->getStorage('gitlab_server')->loadMultiple() as $item) {
      $instances[$item->id()] = $item->label();
    }
    $form['gitlab'] = [
      '#type' => 'select',
      '#title' => $this->t('GitLab Instance'),
      '#options' => $instances,
      '#default_value' => $this->configuration['gitlab'],
      '#required' => TRUE,
      '#weight' => -10,
      '#eca_token_select_option' => TRUE,
    ];
    $form['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of token'),
      '#default_value' => $this->configuration['token_name'],
      '#description' => $this->t('The result of the API call will be stored in this token.'),
      '#required' => TRUE,
      '#weight' => 999,
      '#eca_token_reference' => TRUE,
    ];
    $params = $this->getDefinition('params');
    if (is_array($params)) {
      foreach ($params as $param) {
        $id = 'api_' . $param['name'];
        $form[$id] = [
          '#type' => is_array($param['default']) ? 'textarea' : 'textfield',
          '#title' => $str = mb_convert_case(implode(' ', explode('_', $param['name'])), MB_CASE_TITLE, "UTF-8"),
          '#description' => is_array($param['default']) ? $this->t('Provide a YAML array.') : '',
          '#default_value' => $this->configuration[$id],
          '#required' => !$param['optional'] && !is_array($param['default']),
          '#weight' => $param['position'],
          '#eca_token_replacement' => TRUE,
        ];
      }
    }
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    foreach ($this->configuration as $key => $value) {
      $this->configuration[$key] = $form_state->getValue($key);
    }
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $access = AccessResult::allowed();
    return $return_as_object ? $access : $access->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $object = NULL): void {
    $this->api->switchServer($this->configuration['gitlab']);
    $clientMethod = $this->getDefinition('client_method');
    $apiMethod = $this->getDefinition('api_method');
    $params = $this->getDefinition('params');
    if (!is_array($params)) {
      $params = [];
    }
    usort($params, static function ($f1, $f2) {
      $l1 = (int) $f1['position'];
      $l2 = (int) $f2['position'];
      if ($l1 < $l2) {
        return -1;
      }
      if ($l1 > $l2) {
        return 1;
      }
      return 0;
    });
    $args = [];
    foreach ($params as $param) {
      $id = 'api_' . $param['name'];
      if (is_array($param['default'])) {
        $value = $this->yamlParser->parse($this->configuration[$id]) ?? [];
      }
      else {
        $value = $this->tokenService->replaceClear($this->configuration[$id]);
      }
      if ($value === '' && $param['omittable']) {
        $value = NULL;
      }
      $args[] = $value;
    }

    $callable = [$this->api->getClient()->{$clientMethod}(), $apiMethod];
    if (!is_callable($callable)) {
      throw new \InvalidArgumentException('API method can not be called');
    }
    $result = call_user_func_array($callable, $args);
    $response = $this->api->getClient()->getLastResponse();
    if ($response === NULL || $response->getStatusCode() >= 400) {
      $reason = $response === NULL ?
        'unknown' :
        $response->getReasonPhrase();
      throw new RuntimeException('Error occurred when calling the API: ' . $reason);
    }
    $this->tokenService->addTokenData($this->configuration['token_name'], $result);
  }

}
