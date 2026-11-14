<?php

namespace Drupal\ai_integration_eca\Plugin\Action;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Plugin\ProviderProxy;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for AI-related actions.
 */
abstract class AiActionBase extends ConfigurableActionBase {

  /**
   * The provider manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $aiProvider;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->aiProvider = $container->get('ai.provider');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'model' => '',
      'token_input' => '',
      'token_result' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $models = $this->aiProvider->getSimpleProviderModelOptions($this->getOperationType());

    $form['model'] = [
      '#type' => 'select',
      '#title' => $this->t('Model'),
      '#options' => $models,
      '#default_value' => $this->configuration['model'],
      '#required' => TRUE,
      '#description' => $this->t('The applicable model for this operation type.'),
    ];

    $form['token_input'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Token input'),
      '#default_value' => $this->configuration['token_input'] ?? '',
      '#description' => $this->t('The data input for AI. Accepts a single token holding the value (e.g. <em>[my_input]</em> or <em>[node:body]</em>), or a value typed in directly.'),
      // Not "#eca_token_reference": that flag contracts the field to the bare
      // name of an ECA token, and the modeler refuses "[name]" for it. This
      // field also takes a literal value and a Drupal token, both of which
      // have to be written out in full.
      '#eca_token_replacement' => TRUE,
    ];

    $form['token_result'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Token result'),
      '#default_value' => $this->configuration['token_result'],
      '#description' => $this->t('The response from AI will be stored into the token result field to be used in future steps.'),
      '#eca_token_reference' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::validateConfigurationForm($form, $form_state);

    // Validate that a correct model has been selected.
    $model = explode('__', $form_state->getValue('model'));
    if (count($model) !== 2) {
      $form_state->setErrorByName('model', $this->t('The model "@model" is not configured for this operation (@operation).', [
        '@model' => $form_state->getValue('model'),
        '@operation' => $this->getOperationType(),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['model'] = $form_state->getValue('model');
    $this->configuration['token_input'] = $form_state->getValue('token_input');
    $this->configuration['token_result'] = $form_state->getValue('token_result');
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $dependencies = parent::calculateDependencies();

    // This method has to be total. DependentPluginInterface declares no
    // exception, and core calls it on every plugin of a config entity through
    // \Drupal\Core\Plugin\PluginDependencyTrait without a try/catch anywhere on
    // the chain: on every untrusted save, and again while resolving what to
    // change when a module is uninstalled
    // (\Drupal\Core\Config\ConfigManager::getConfigEntitiesToChangeOnDependencyRemoval()
    // and ::uninstall(), which re-saves the fixed entities). Throwing here
    // therefore does not just fail this plugin, it breaks saving any ECA model
    // containing the action and can abort a site-wide module uninstall.
    //
    // No model selected yet is a legitimate state: 'model' defaults to an empty
    // string, and the modeler creates the action before a model is picked. Such
    // an action simply has no provider to depend on.
    try {
      $providerId = $this->getModelData()['provider_id'];
    }
    catch (\InvalidArgumentException) {
      return $dependencies;
    }

    // The provider plugin may be absent, for example when a model authored on a
    // site with additional provider modules is imported here, or when the
    // provider module has since been removed. Such a dependency cannot be
    // recorded, but it must not throw either.
    $definition = $this->aiProvider->getDefinition($providerId, FALSE);
    if (is_array($definition) && !empty($definition['provider'])) {
      $dependencies['module'][] = $definition['provider'];
    }

    return $dependencies;
  }

  /**
   * Get the data of the configured model.
   *
   * @param string|null $source
   *   The optional source string.
   *
   * @return array{provider_id: string, model_id: string}
   *   Returns an array containing the provider ID and the model ID.
   *
   * @throws \InvalidArgumentException
   *   When the source is not a "provider_id__model_id" string, which includes
   *   the empty default used until a model has been selected. Every caller has
   *   to handle this.
   */
  protected function getModelData(?string $source = NULL): array {
    // Coalesce a missing 'model' key so that a malformed configuration reports
    // the exception below rather than a PHP "undefined array key" warning.
    $source = $source ?? $this->configuration['model'] ?? '';
    $data = explode('__', $source);
    if (count($data) !== 2) {
      throw new \InvalidArgumentException('Given source "' . $source . '" is not valid. Could not determine provider and model.');
    }
    return array_combine(
      ['provider_id', 'model_id'],
      $data
    );
  }

  /**
   * Loads the configured model provider.
   *
   * @param string|null $source
   *   The optional source string for the model.
   *
   * @return \Drupal\ai\Plugin\ProviderProxy
   *   Returns the model provider.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function loadModelProvider(?string $source = NULL): ProviderProxy {
    $data = $this->getModelData($source);
    // Core will handle any error if the plugin ID ($model['provider']) can not
    // be instantiated.
    return $this->aiProvider->createInstance($data['provider_id']);
  }

  /**
   * Resolves a configured value into the data it stands for.
   *
   * The value holds one of three things: the name of an ECA token, a string
   * containing Drupal tokens, or a literal value. The token stack is asked
   * first, because only that hands back the data as it is held - a list, an
   * entity, a data transfer object - where replacement would flatten it into a
   * string. Only when the value names no ECA token is it run through
   * replacement, which resolves "[node:title]" and leaves literal text alone.
   *
   * @param string $configured
   *   The configured value.
   *
   * @return mixed
   *   The token data, the replaced string, or NULL when nothing is configured
   *   or the result is empty.
   */
  protected function resolveTokenValue(string $configured): mixed {
    $configured = trim($configured);
    if ($configured === '') {
      return NULL;
    }

    $tokenName = $this->tokenNameFrom($configured);
    if ($tokenName !== NULL) {
      $data = $this->tokenService->getTokenData($tokenName);
      // A name that holds no data is not an ECA token - it may still be a
      // Drupal token such as [site:name], or a literal value - so fall through
      // to replacement.
      if ($data !== NULL) {
        return $data;
      }
    }

    $replaced = trim((string) $this->tokenService->replace($configured));
    return $replaced === '' ? NULL : $replaced;
  }

  /**
   * Resolves a configured value into a string.
   *
   * @param string $configured
   *   The configured value.
   *
   * @return string
   *   The resolved value as a string, or an empty string when unavailable.
   */
  protected function resolveTokenString(string $configured): string {
    return $this->tokenValueToString($this->resolveTokenValue($configured));
  }

  /**
   * Reads a resolved token value as a scalar string.
   *
   * Token data for the file/text inputs is expected to be a string or integer,
   * but the token service wraps scalars in a typed-data object (for which
   * ->getString() is the contract). This unwraps either form safely; a plain
   * scalar, as replacement hands back, is returned as-is.
   *
   * @param mixed $value
   *   The resolved value.
   *
   * @return string
   *   The value as a string, or an empty string when it holds none.
   */
  protected function tokenValueToString(mixed $value): string {
    // ECA unwraps any token whose value is an entity to the entity object
    // itself (see TokenServices::getTokenData()). A managed-file token
    // therefore arrives as a File entity, not a scalar fid.
    if ($value instanceof EntityInterface) {
      return (string) $value->id();
    }
    if ($value instanceof TypedDataInterface) {
      return (string) $value->getString();
    }
    return is_scalar($value) ? (string) $value : '';
  }

  /**
   * Reads a configured value as the name of a single token, if it could be one.
   *
   * Both spellings ECA site builders use are accepted: bare, the way every
   * other token-reference field in ECA is filled in, and wrapped in brackets,
   * the way a token is written inside a string. The guard is deliberately
   * negative - only whitespace and a slash rule a value out - because ECA's own
   * token lookup normalizes a bare name itself: it ignores case and reads "."
   * the way it reads ":". Names written that way have always resolved here, so
   * narrowing the accepted shape would silently stop reading tokens that work
   * today. What decides that a candidate is not an ECA token is the lookup
   * coming back empty, not its spelling.
   *
   * @param string $configured
   *   The trimmed configuration value.
   *
   * @return string|null
   *   The token name, or NULL when the value cannot be one.
   *
   * @see \Drupal\eca\Token\TokenDecoratorTrait::normalizeKey()
   */
  protected function tokenNameFrom(string $configured): ?string {
    if (preg_match('/^\[([^\[\]]+)\]$/', $configured, $matches) === 1) {
      return $matches[1];
    }
    // Whitespace means prose rather than a name, and a slash means a path or a
    // stream URI: neither is ever looked up as a token.
    if ($configured === '' || preg_match('#[\s/]#', $configured) === 1) {
      return NULL;
    }
    return $configured;
  }

  /**
   * Reads a token value as a scalar string.
   *
   * Retained for actions outside this module that extend this base class. In
   * here, ::resolveTokenString() is what reads a configured field, because a
   * field takes more than a token name now.
   *
   * @param string $tokenName
   *   The token name to read.
   *
   * @return string
   *   The token value as a string, or an empty string when unavailable.
   */
  protected function getTokenString(string $tokenName): string {
    return $this->tokenValueToString($this->tokenService->getTokenData($tokenName));
  }

  /**
   * Get the applicable operation type.
   *
   * @return string
   *   Returns the operation type of the Action.
   */
  abstract protected function getOperationType(): string;

}
