<?php

declare(strict_types=1);

namespace Drupal\ai_eca_interceptor\EventSubscriber;

use Drupal\ai\Event\PreGenerateResponseEvent;
use Drupal\ai\EventSubscriber\GuardrailsEventSubscriber;
use Drupal\ai\OperationType\InputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Applies pre-generate guardrails that were attached during ECA processing.
 *
 * The core GuardrailsEventSubscriber runs at default priority (0), which is
 * the same priority as ECA's DynamicSubscriber. Because the ai module's
 * services are registered first, GuardrailsEventSubscriber fires before ECA
 * actions execute. If an ECA action attaches a guardrail set via
 * AttachGuardrailSetAction, the core subscriber has already returned early
 * (finding no guardrail set).
 *
 * This subscriber runs at priority -10 — after ECA has finished, before the
 * reroute subscriber at -100 — and delegates to the core subscriber to apply
 * any pre-generate guardrails that were attached during ECA.
 *
 * Post-generate guardrails are unaffected because they fire on a separate
 * event (PostGenerateResponseEvent) where the guardrail set is already present
 * on the input object.
 */
class GuardrailApplierSubscriber implements EventSubscriberInterface {

  /**
   * Constructs the subscriber.
   */
  public function __construct(
    protected readonly GuardrailsEventSubscriber $guardrailsSubscriber,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PreGenerateResponseEvent::EVENT_NAME => ['applyDeferredGuardrails', -10],
    ];
  }

  /**
   * Applies pre-generate guardrails attached after the core subscriber ran.
   */
  public function applyDeferredGuardrails(PreGenerateResponseEvent $event): void {
    $input = $event->getInput();
    if (!$input instanceof InputInterface || $input->getGuardrailSets() === []) {
      return;
    }
    // If the core subscriber already processed pre-generate guardrails
    // (e.g. guardrail set was attached in code before the provider call),
    // don't run them again.
    $results = $input->getAllGuardrailResults();
    if (!empty($results['pre'])) {
      return;
    }
    $this->guardrailsSubscriber->applyPreGenerateGuardrails($event);
  }

}
