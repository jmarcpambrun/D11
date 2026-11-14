<?php

declare(strict_types=1);

namespace Drupal\ai_eca_interceptor\Plugin\Action;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\OutputInterface;
use Drupal\ai_eca_interceptor\FailoverGuard;
use Drupal\ai_eca_interceptor\ProviderOperationMethod;
use Drupal\ai_eca_interceptor\TagNormalizer;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Action: fail over a failed AI call to a backup provider and model.
 *
 * Operates on the AI exception event (AI response: failed). It retries the
 * same operation and input against a configured backup provider/model and, if
 * that succeeds, sets the result as the forced output so the AI module returns
 * it to the caller instead of re-throwing the original exception.
 *
 * The recursion guard that prevents the backup call (which itself dispatches an
 * AiExceptionEvent if it fails) from re-triggering this action lives in the
 * shared ai_eca_interceptor.failover_guard service, because ECA creates a fresh
 * plugin instance per invocation.
 *
 * Both provider_id and model_id are required because model ids are not portable
 * between providers.
 */
#[Action(
  id: 'ai_eca_interceptor_failover_to_provider',
  label: new TranslatableMarkup('AI response failed: fail over to provider/model'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Recover from a failed call by retrying the same request against a backup provider and model, returning its output (both required).'),
  version_introduced: '2.0.0',
)]
class FailoverToProviderAction extends AiEventActionBase {

  /**
   * Metadata key recording a successful failover.
   */
  public const META_EXECUTED = 'ai_eca_interceptor.failover.executed';

  /**
   * Metadata key recording a failover that could not recover the call.
   */
  public const META_FAILED = 'ai_eca_interceptor.failover.failed';

  /**
   * The AI provider plugin manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $providerManager;

  /**
   * Registry of backup targets currently being attempted.
   *
   * Keyed by "provider_id:model_id" and reference counted, so the same backup
   * can be attempted at nested levels (e.g. recovering both the primary call
   * and, separately, a guardrail classifier call made while the backup runs)
   * and released correctly.
   *
   * An earlier implementation used a single global "in progress" flag, which
   * suppressed every nested AI failure while any failover ran. That broke a
   * real case: an LLM-based guardrail evaluated on the backup request may
   * classify the prompt with its own chat call, and if that call's provider was
   * down its failure could not be recovered, aborting the whole backup.
   * Tracking the specific backup targets instead means only a call that fails
   * on a backup target we are *already attempting* is suppressed — that is the
   * backup failing on its own target, the genuine loop this guard must break.
   * Any other failure, including the guardrail classifier call (even when it
   * reuses the provider/model that originally failed), is free to fail over.
   *
   * @var \Drupal\ai_eca_interceptor\FailoverGuard
   */
  protected FailoverGuard $failoverGuard;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->providerManager = $container->get('ai.provider');
    $instance->failoverGuard = $container->get('ai_eca_interceptor.failover_guard');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $object = NULL): void {
    $event = $this->failedEvent();
    if (!$event) {
      return;
    }
    // Do not override a forced output another action already supplied.
    if ($event->getForcedOutputObject()) {
      return;
    }
    // Suppress only when the call that just failed is itself a backup target we
    // are currently attempting: that is the backup failing on its own target,
    // the loop this guard exists to break. Any other failure — including a
    // guardrail classifier call made while the backup runs — is free to fail
    // over on its own, even if it reuses the provider/model that first failed.
    $failedKey = $event->getProviderId() . ':' . $event->getModelId();
    if ($this->failoverGuard->isActive($failedKey)) {
      return;
    }
    // replaceClear(), matching ::access(). See the note there on why the plain
    // replace() would be wrong. The null coalescing also matches ::access():
    // under strict_types a missing key would be a TypeError inside
    // replaceClear(), and a direct caller skipping ::access() is exactly the
    // path this defensive re-check exists to protect.
    $providerId = trim((string) $this->tokenService->replaceClear($this->configuration['provider_id'] ?? ''));
    $modelId = trim((string) $this->tokenService->replaceClear($this->configuration['model_id'] ?? ''));
    if ($providerId === '' || $modelId === '') {
      return;
    }
    // Shared with ProviderRerouteSubscriber: both re-invoke the current
    // operation on another provider and must derive the same method name.
    $method = ProviderOperationMethod::fromOperationType($event->getOperationType());
    $targetKey = $providerId . ':' . $modelId;

    try {
      $provider = $this->providerManager->createInstance($providerId);
      // ProviderProxy exposes provider operations via __call, so we rely on the
      // call to throw rather than a method_exists check.
      if (method_exists($provider, 'setConfiguration')) {
        $provider->setConfiguration($event->getConfiguration());
      }

      // Mark the backup target so that, if it fails on its own target, the
      // resulting exception event is recognized as recursion and suppressed
      // above rather than triggering yet another identical failover.
      //
      // The reference count is balanced by this try/finally alone. That is
      // deliberate and sufficient: finally runs on every path out of the call,
      // including a thrown exception. Do not add an ECA CleanupInterface hook
      // on top — ECA calls it from a recursive finally with a fresh plugin
      // instance, so the nested run would clear the guard the outer, still
      // executing failover depends on.
      $this->failoverGuard->enter($targetKey);
      try {
        // Tags arrive in a mixed shape once SetTagAction has run, so resolve
        // the tag name per entry rather than assuming a list or a keyed array.
        $tagList = TagNormalizer::normalize($event->getTags());
        $output = $provider->$method($event->getInput(), $modelId, $tagList);
      }
      finally {
        $this->failoverGuard->release($targetKey);
      }

      if ($output instanceof OutputInterface) {
        $event->setForcedOutputObject($output);
        $event->setMetadata(self::META_EXECUTED, [
          'provider_id' => $providerId,
          'model_id' => $modelId,
        ]);
      }
      else {
        // The backup answered with something the AI module cannot return, so
        // the original exception still propagates. Record it like any other
        // failure so a downstream ECA condition can branch on it.
        $event->setMetadata(self::META_FAILED, [
          'provider_id' => $providerId,
          'model_id' => $modelId,
          'message' => 'The backup provider did not return a usable output object.',
        ]);
      }
    }
    catch (\Throwable $e) {
      // The exception is swallowed on purpose: this action runs on the AI
      // exception event, where re-throwing would replace the provider's own
      // failure with ours and lose the original cause. Instead the failure is
      // logged and recorded in event metadata under META_FAILED, so an ECA
      // model can branch on it (e.g. notify, or try a second backup) exactly
      // like it can branch on the reroute subscriber's
      // ai_eca_interceptor.reroute.executed key.
      $this->logger->error('AI ECA failover to @provider/@model failed: @message', [
        '@provider' => $providerId,
        '@model' => $modelId,
        '@message' => $e->getMessage(),
      ]);
      $event->setMetadata(self::META_FAILED, [
        'provider_id' => $providerId,
        'model_id' => $modelId,
        'message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * {@inheritdoc}
   *
   * ECA calls setEvent() before access(), so token replacement resolves against
   * the live request here. The configured ids must therefore be evaluated
   * *after* token replacement — checking the raw configuration would grant
   * access to a value such as "[event:provider_id]" that resolves to an empty
   * string, and execute() would then return silently with nothing logged.
   *
   * Resolution uses replaceClear(), not replace(): the plain replace() leaves a
   * token LITERAL when no replacement value can be generated, so a token that
   * resolves to nothing (or a typo like "[event:model]") would survive as a
   * non-empty string, pass this check, and only fail later inside the provider
   * lookup. replaceClear() removes such tokens, which is what makes the
   * emptiness check meaningful. ::execute() uses the same method — the two
   * disagreeing is the exact bug this check exists to prevent.
   *
   * Every denial carries a reason so ECA can report it: EcaAction::execute()
   * logs it and the process debugger records the action as access-denied
   * rather than executed.
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($this->failedEvent() === NULL) {
      $result = AccessResult::forbidden('This action is only available on the "AI response: failed" event.');
    }
    else {
      $provider = trim((string) $this->tokenService->replaceClear($this->configuration['provider_id'] ?? ''));
      $model = trim((string) $this->tokenService->replaceClear($this->configuration['model_id'] ?? ''));
      if ($provider === '') {
        $result = AccessResult::forbidden('The backup provider ID is empty after token replacement.');
      }
      elseif ($model === '') {
        $result = AccessResult::forbidden('The backup model ID is empty after token replacement.');
      }
      else {
        $result = AccessResult::allowed();
      }
    }
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'provider_id' => '',
      'model_id' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['provider_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Backup provider id'),
      '#default_value' => $this->configuration['provider_id'],
      '#description' => $this->t('The provider id to fail over to when the original call throws.'),
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    $form['model_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Backup model id'),
      '#default_value' => $this->configuration['model_id'],
      '#description' => $this->t('The model id on that provider. Model ids are not portable between providers, so this must be set explicitly.'),
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['provider_id'] = $form_state->getValue('provider_id');
    $this->configuration['model_id'] = $form_state->getValue('model_id');
    parent::submitConfigurationForm($form, $form_state);
  }

}
