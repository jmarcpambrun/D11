<?php

declare(strict_types=1);

namespace Drupal\ai_eca_interceptor\EventSubscriber;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Event\PreGenerateResponseEvent;
use Drupal\ai\OperationType\OutputInterface;
use Drupal\ai_eca_interceptor\Plugin\Action\RerouteAction;
use Drupal\ai_eca_interceptor\ProviderOperationMethod;
use Drupal\ai_eca_interceptor\TagNormalizer;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Executes reroutes requested by ECA RerouteAction via event metadata.
 *
 * Runs at a low priority so it fires AFTER ECA has executed its actions on the
 * PreGenerateResponseEvent. When it detects reroute metadata, it instantiates
 * the desired provider, invokes the matching operation with the current input,
 * and sets the result as the forced output on the original event. The
 * ProviderProxy will then short-circuit the original provider call.
 */
class ProviderRerouteSubscriber implements EventSubscriberInterface {

  /**
   * Recursion guard.
   *
   * Instance state, not static: this subscriber is registered as the singleton
   * service ai_eca_interceptor.reroute_subscriber, so one instance already sees
   * every dispatch, and RerouteAction reads the flag through the same injected
   * service. Keeping it out of static scope means nothing but ::onPreGenerate()
   * can ever change it.
   */
  private bool $rerouting = FALSE;

  public function __construct(
    protected AiProviderPluginManager $providerManager,
    protected LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PreGenerateResponseEvent::EVENT_NAME => ['onPreGenerate', -100],
    ];
  }

  /**
   * Check whether a reroute is currently in progress (public for actions).
   *
   * @return bool
   *   TRUE while ::onPreGenerate() is executing a rerouted provider call.
   */
  public function isRerouting(): bool {
    return $this->rerouting;
  }

  /**
   * {@inheritdoc}
   */
  public function onPreGenerate(PreGenerateResponseEvent $event): void {
    if ($this->rerouting) {
      return;
    }
    if ($event->getForcedOutputObject()) {
      return;
    }

    $providerId = $event->getMetadata(RerouteAction::META_PROVIDER);
    $modelId = $event->getMetadata(RerouteAction::META_MODEL);
    // RerouteAction always sets both keys together. Bail if neither is set.
    if (!$providerId || !$modelId) {
      return;
    }
    $operationType = $event->getOperationType();
    // Shared with FailoverToProviderAction: both re-invoke the current
    // operation on another provider and must derive the same method name.
    $method = ProviderOperationMethod::fromOperationType($operationType);

    try {
      $provider = $this->providerManager->createInstance($providerId);
      // ProviderProxy exposes provider methods via __call, so we rely on the
      // underlying call to throw rather than a method_exists check.
      if (method_exists($provider, 'setConfiguration')) {
        $provider->setConfiguration($event->getConfiguration());
      }

      // The guard is balanced by this try/finally alone. That is deliberate and
      // sufficient: finally runs on every path out of the call, including a
      // thrown exception, so the flag can never be left set. Do not add an
      // ECA CleanupInterface hook on top — ECA calls it from a recursive
      // finally with a fresh plugin instance, so a nested model run would clear
      // this flag while the outer reroute is still executing.
      $this->rerouting = TRUE;
      try {
        // Tags arrive in a mixed shape once SetTagAction has run, so resolve
        // the tag name per entry rather than assuming a list or a keyed array.
        // Note this deliberately does not call $event->setTags(): that replaces
        // the event's tags wholesale and would be visible to every later
        // listener.
        $tagList = TagNormalizer::normalize($event->getTags());
        $output = $provider->$method($event->getInput(), $modelId, $tagList);
      }
      finally {
        $this->rerouting = FALSE;
      }

      if ($output instanceof OutputInterface) {
        $event->setForcedOutputObject($output);
        $event->setMetadata('ai_eca_interceptor.reroute.executed', [
          'provider_id' => $providerId,
          'model_id' => $modelId,
        ]);
      }
    }
    catch (\Throwable $e) {
      $this->logger->error('AI ECA reroute failed: @message', ['@message' => $e->getMessage()]);
    }
  }

}
