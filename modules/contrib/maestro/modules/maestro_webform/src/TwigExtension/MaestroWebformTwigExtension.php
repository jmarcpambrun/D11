<?php

namespace Drupal\maestro_webform\TwigExtension;

use Drupal\maestro\Engine\MaestroEngine;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension for Maestro webform task context.
 *
 * Provides maestro_entity_field() and maestro_entity_id() for use in Webform
 * Computed Twig elements where Drupal token replacement does not run before Twig.
 */
class MaestroWebformTwigExtension extends AbstractExtension {

  /**
   * {@inheritdoc}
   */
  public function getFunctions(): array {
    return [
      new TwigFunction('maestro_entity_field', [$this, 'getEntityField']),
      new TwigFunction('maestro_entity_id', [$this, 'getEntityId']),
    ];
  }

  /**
   * Returns a field value from a Maestro entity identifier.
   *
   * Derives queue/process context from the Maestro sitewide token in the
   * current request URL, so no extra arguments are needed.
   *
   * Usage in Computed Twig:
   * @code
   *   {{ maestro_entity_field('your_unique_id', 'field_title') }}
   *   {% set nid = maestro_entity_field('your_unique_id', 'nid') %}
   * @endcode
   *
   * @param string $uniqueIdentifier
   *   The entity identifier machine name configured on the Maestro task.
   * @param string $fieldName
   *   The node field machine name or webform submission element key.
   *
   * @return string
   *   The resolved field value, or an empty string if not found.
   */
  public function getEntityField(string $uniqueIdentifier, string $fieldName): string {
    $processID = $this->resolveProcessId();
    if (!$processID) {
      return '';
    }
    return _maestro_webform_resolve_entity_field($processID, $uniqueIdentifier, $fieldName) ?? '';
  }

  /**
   * Returns the raw entity ID from a Maestro entity identifier.
   *
   * Shorthand for when you only need the entity ID (e.g. a node NID) without
   * having to specify a field name.
   *
   * Usage in Computed Twig:
   * @code
   *   {{ drupal_entity('node', maestro_entity_id('your_unique_id'), 'teaser') }}
   * @endcode
   *
   * @param string $uniqueIdentifier
   *   The entity identifier machine name configured on the Maestro task.
   *
   * @return string
   *   The entity ID, or an empty string if not found.
   */
  public function getEntityId(string $uniqueIdentifier): string {
    $processID = $this->resolveProcessId();
    if (!$processID) {
      return '';
    }
    $data = MaestroEngine::getEntityIdentiferFieldsByUniqueID($processID, $uniqueIdentifier);
    return (string) ($data[$uniqueIdentifier]['entity_id'] ?? '');
  }

  /**
   * Resolves the Maestro process ID from the current request URL.
   */
  protected function resolveProcessId(): ?int {
    $config = \Drupal::config('maestro.settings');
    $sitewideToken = $config->get('maestro_sitewide_token');
    if (empty($sitewideToken)) {
      return NULL;
    }

    $tokenValue = \Drupal::request()->query->get($sitewideToken, '');
    if (empty($tokenValue)) {
      return NULL;
    }

    $queueID = MaestroEngine::getQueueIdFromToken($tokenValue);
    if (!$queueID) {
      return NULL;
    }

    $processID = MaestroEngine::getProcessIdFromQueueId($queueID);
    return $processID ?: NULL;
  }

}
