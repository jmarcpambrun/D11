<?php

declare(strict_types=1);

namespace Drupal\ai_eca_interceptor;

/**
 * Maps an AI operation type to the provider method that implements it.
 *
 * The AI module names an operation type in snake_case — it is a machine name,
 * carried on every request event as
 * \Drupal\ai\Event\AiProviderRequestBaseEvent::getOperationType() and used as a
 * plugin-definition key (`speech_to_text`, `image_classification`, …). The
 * method a provider exposes for that same operation is the camelCase form
 * (`speechToText()`, `imageClassification()`), because that is the PHP method
 * name declared by the operation-type interface. Nothing in the AI module
 * publishes the mapping between the two, so a caller that wants to invoke an
 * operation it only knows by machine name has to derive the method name itself.
 *
 * Two places in this module do exactly that, and they must agree: this module
 * re-invokes the *current* request's operation against a different provider, in
 * \Drupal\ai_eca_interceptor\EventSubscriber\ProviderRerouteSubscriber (a
 * proactive reroute before the provider is called) and in
 * \Drupal\ai_eca_interceptor\Plugin\Action\FailoverToProviderAction (a recovery
 * retry after the call has thrown). Both dispatch dynamically —
 * `$provider->$method(...)` — because the operation type is only known at
 * runtime. If their two derivations ever drifted apart, reroute and failover
 * would resolve the same operation to different methods, and the discrepancy
 * would surface only as a "method not found" error on whichever operation type
 * happened to expose it. Keeping the rule in one place removes that class of
 * bug entirely, the same way \Drupal\ai_eca_interceptor\TagNormalizer does for
 * the tag array shape both call sites also share.
 *
 * @see \Drupal\ai_eca_interceptor\EventSubscriber\ProviderRerouteSubscriber
 * @see \Drupal\ai_eca_interceptor\Plugin\Action\FailoverToProviderAction
 * @see \Drupal\ai\Event\AiProviderRequestBaseEvent::getOperationType()
 */
final class ProviderOperationMethod {

  /**
   * Converts an operation type (snake_case) to a provider method (camelCase).
   *
   * Only `_` followed by a lowercase letter is folded, which is the entire
   * shape an AI operation-type machine name can take. Anything already in
   * camelCase is therefore returned untouched, so the conversion is idempotent
   * and safe to apply to a value of either form.
   *
   * @param string $operationType
   *   The AI operation type machine name, e.g. "speech_to_text".
   *
   * @return string
   *   The provider method name, e.g. "speechToText".
   */
  public static function fromOperationType(string $operationType): string {
    return preg_replace_callback(
      '/_([a-z])/',
      static fn(array $m) => strtoupper($m[1]),
      $operationType,
    );
  }

}
