<?php

namespace Drupal\ai_integration_eca\Service;

/**
 * Builds compact prompt context out of a modeler graph snapshot.
 */
interface ModelerContextBuilderInterface {

  /**
   * Builds the prompt context for a modeler graph snapshot.
   *
   * @param array $snapshot
   *   The graph snapshot as sent by the modeler plugin. All keys are optional;
   *   recognized keys are "model", "nodes", "edges", "selection",
   *   "componentLabels" and "components".
   * @param string $prompt
   *   The question the user asked. It is not part of the description, it only
   *   decides which of the available components are worth describing. An
   *   empty prompt is allowed.
   *
   * @return string
   *   A plain text description of the model, ready to be embedded into a
   *   prompt. The result never exceeds the character budget of the
   *   implementation.
   */
  public function build(array $snapshot, string $prompt = ''): string;

}
