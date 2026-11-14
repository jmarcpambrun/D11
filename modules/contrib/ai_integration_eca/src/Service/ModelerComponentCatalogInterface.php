<?php

namespace Drupal\ai_integration_eca\Service;

/**
 * Renders the components a model may be built from.
 *
 * A site can offer several hundred components, which is far more than is
 * worth putting in front of a provider for a single question. This service
 * picks the ones that are plausibly relevant and describes them in one line
 * each, so that the assistant can name real plugin identifiers instead of
 * inventing ones that look right.
 */
interface ModelerComponentCatalogInterface {

  /**
   * Renders the relevant part of the component catalog.
   *
   * @param mixed $components
   *   The catalog as sent by the modeler plugin, normally the result of the
   *   plugin API's getComponents(). Anything unusable yields an empty string.
   * @param string $prompt
   *   The question the user asked, used to rank the catalog. An empty prompt
   *   is allowed and simply leaves the catalog in its original order.
   * @param array $usedPlugins
   *   The plugin identifiers already present in the model. These are always
   *   described, whether or not they match the question, because the answer
   *   will usually have to talk about them.
   *
   * @return string
   *   A plain text description of the selected components, or an empty string
   *   when there is nothing to describe. The result never exceeds the
   *   character budget of the implementation.
   */
  public function render(mixed $components, string $prompt, array $usedPlugins): string;

}
