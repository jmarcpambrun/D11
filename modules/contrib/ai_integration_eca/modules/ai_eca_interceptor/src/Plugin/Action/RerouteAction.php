<?php

declare(strict_types=1);

namespace Drupal\ai_eca_interceptor\Plugin\Action;

use Drupal\ai_eca_interceptor\EventSubscriber\ProviderRerouteSubscriber;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaAction;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Action: reroute an AI request to a different provider and model.
 *
 * Operates on the pre-request event (AI request: before provider call). The
 * action stores reroute intent in event metadata; ProviderRerouteSubscriber
 * runs at a later priority on the same PreGenerateResponseEvent and performs
 * the actual swap by invoking the target provider and calling
 * setForcedOutputObject() on the original event.
 *
 * Both provider_id and model_id are required because model ids are not
 * portable between providers.
 *
 * Note: this action reroutes proactively, before the provider is called. To
 * recover after a call has thrown, use FailoverToProviderAction on the
 * "AI response: failed" event instead.
 */
#[Action(
  id: 'ai_eca_interceptor_reroute',
  label: new TranslatableMarkup('AI request: reroute provider/model'),
)]
#[EcaAction(
  description: new TranslatableMarkup('Reroute the current AI request to a different provider and model (both required).'),
  version_introduced: '2.0.0',
)]
class RerouteAction extends AiEventActionBase {

  public const META_PROVIDER = 'ai_eca_interceptor.reroute.provider_id';
  public const META_MODEL = 'ai_eca_interceptor.reroute.model_id';

  /**
   * The subscriber that executes the reroute.
   *
   * @var \Drupal\ai_eca_interceptor\EventSubscriber\ProviderRerouteSubscriber
   */
  protected ProviderRerouteSubscriber $rerouteSubscriber;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->rerouteSubscriber = $container->get('ai_eca_interceptor.reroute_subscriber');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $object = NULL): void {
    $event = $this->preEvent();
    if (!$event) {
      return;
    }
    // Defensive only. ::access() is the observable gate for all of these
    // preconditions, so ECA reports and logs them instead of recording this
    // action as executed successfully. Re-checking here keeps direct callers
    // (tests, other code invoking execute() without access()) safe.
    if ($this->rerouteSubscriber->isRerouting()) {
      return;
    }
    // replaceClear(), matching ::access(). See the note there on why the plain
    // replace() would be wrong. The null coalescing also matches ::access():
    // under strict_types a missing key would be a TypeError inside
    // replaceClear(), and a direct caller skipping ::access() is exactly the
    // path this defensive re-check exists to protect.
    $provider = trim((string) $this->tokenService->replaceClear($this->configuration['provider_id'] ?? ''));
    $model = trim((string) $this->tokenService->replaceClear($this->configuration['model_id'] ?? ''));
    // Model ids are not portable between providers, so both values are
    // required to perform a reroute.
    if ($provider === '' || $model === '') {
      return;
    }
    $event->setMetadata(self::META_PROVIDER, $provider);
    $event->setMetadata(self::META_MODEL, $model);
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
   * resolves to nothing (or a typo like "[event:provider]") survives as a
   * non-empty string, passes this check, and only fails later in the provider
   * lookup. replaceClear() removes such tokens, which is what makes the
   * emptiness check meaningful. ::execute() uses the same method — the two
   * disagreeing is the exact bug this check exists to prevent.
   *
   * Every denial carries a reason so ECA can report it: EcaAction::execute()
   * logs it and the process debugger records the action as access-denied
   * rather than executed.
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($this->preEvent() === NULL) {
      $result = AccessResult::forbidden('This action is only available on the "AI request: before provider call" event.');
    }
    elseif ($this->rerouteSubscriber->isRerouting()) {
      $result = AccessResult::forbidden('A reroute is already being executed for this AI request, so rerouting again would recurse.');
    }
    else {
      $provider = trim((string) $this->tokenService->replaceClear($this->configuration['provider_id'] ?? ''));
      $model = trim((string) $this->tokenService->replaceClear($this->configuration['model_id'] ?? ''));
      if ($provider === '') {
        $result = AccessResult::forbidden('The target provider ID is empty after token replacement.');
      }
      elseif ($model === '') {
        $result = AccessResult::forbidden('The target model ID is empty after token replacement.');
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
      '#title' => $this->t('Provider id'),
      '#default_value' => $this->configuration['provider_id'],
      '#description' => $this->t('The target provider id to route the request to.'),
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    $form['model_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Model id'),
      '#default_value' => $this->configuration['model_id'],
      '#description' => $this->t('The target model id on that provider. Model ids are not portable between providers, so this must be set explicitly.'),
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
