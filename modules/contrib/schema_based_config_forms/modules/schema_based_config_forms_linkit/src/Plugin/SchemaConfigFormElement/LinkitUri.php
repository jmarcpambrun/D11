<?php

namespace Drupal\schema_based_config_forms_linkit\Plugin\SchemaConfigFormElement;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\linkit\Utility\LinkitHelper;

/**
 * Provides form element building for URI-based, validated Linkit fields.
 *
 * @SchemaConfigFormElement(
 *   id = "linkit_uri",
 *   title = @Translation("Linkit URI"),
 *   description = @Translation("Provides form element building for Linkit fields. Values are validated and stored as URIs."),
 *   types = {
 *     "linkit_uri",
 *   },
 *   weight = 10,
 * )
 */
class LinkitUri extends LinkitUrl {

  /**
   * Validation callback that transforms entity paths to URIs.
   */
  public static function transformValueToUri(array &$element, FormStateInterface $form_state, array &$complete_form): void {
    $value = $form_state->getValue($element['#parents']);
    if ($value) {
      $uri = LinkitHelper::uriFromUserInput($value);
      if (!$uri) {
        $form_state->setError($element, t(
          'Invalid URL @value - cannot be transformed to URI',
          ['@value' => $value]
        ));
        return;
      }
      $form_state->setValueForElement($element, $uri);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildElement(array $field_schema, $default_value, array $type_schema = []): ?array {
    // Linkit elements submit the user-facing path, e.g. /node/2. However, many
    // link generation processes in Drupal - like Url::fromUri() - require a
    // URI, e.g. entity:node/2 for a canonical node link. It is therefore more
    // helpful to store the URI, which guarantees that the value will be valid
    // for Url::fromUri().
    if ($default_value) {
      $default_url = Url::fromUri($default_value, ['path_processing' => FALSE]);
      if (!$default_url->isExternal()) {
        $default_value = rawurldecode($default_url->toString());
      }
    }

    $element = parent::buildElement($field_schema, $default_value, $type_schema);

    $element['#element_validate'][] = [static::class, 'transformValueToUri'];

    return $element;
  }

}
