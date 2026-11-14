<?php

namespace Drupal\ai_integration_eca\Exception;

/**
 * Thrown when no usable chat provider is configured on the site.
 *
 * This is a configuration problem rather than a runtime failure, so callers
 * are expected to catch it and tell the user what to fix instead of logging a
 * stack trace and showing a generic error.
 */
class ChatProviderUnavailableException extends \RuntimeException {

}
