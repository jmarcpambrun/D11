<?php

namespace Drupal\ai_integration_eca\Service;

/**
 * Validates a plan of graph operations proposed by the assistant.
 *
 * A plan is applied to the canvas in one go, so a plan that fails halfway
 * through leaves the user with a model that is neither what they had nor what
 * they agreed to. That is worse than being told no plan is on offer, which is
 * why validation here is all or nothing: one unusable operation rejects the
 * whole plan and the reason is handed back for the user to read.
 *
 * Extraction of the plan from the reply is somebody else's job. This service
 * only ever sees decoded data, so it stays useful should a provider ever hand
 * the plan over through a native structured output mode instead of a fenced
 * code block.
 */
interface ModelerPlanValidatorInterface {

  /**
   * Validates a proposed plan against the model on the canvas.
   *
   * @param mixed $operations
   *   The operations as they came out of the envelope. NULL or a missing
   *   value means no plan was proposed, which is not an error.
   * @param array $context
   *   What the plan may refer to. All keys are optional:
   *   - nodes: The IDs of the components currently on the canvas.
   *   - edges: The IDs of the connections currently on the canvas.
   *   - plugins: The plugin IDs this site offers. When empty, the check that
   *     every plugin exists is skipped rather than failing every plan, so a
   *     browser that sent no catalog still gets a usable answer.
   *
   * @return array
   *   An array with these keys:
   *   - operations: The validated plan, ready to be executed in order. Empty
   *     when no plan was proposed or the plan was rejected.
   *   - reason: One of the REASON_* constants when a proposed plan was
   *     rejected, or an empty string. Callers turn this into a sentence the
   *     user can read; it is never surfaced as an error status.
   */
  public function validate(mixed $operations, array $context): array;

}
