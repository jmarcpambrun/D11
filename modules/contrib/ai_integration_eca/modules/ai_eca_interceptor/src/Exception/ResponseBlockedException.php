<?php

declare(strict_types=1);

namespace Drupal\ai_eca_interceptor\Exception;

/**
 * Thrown when an ECA rule blocks an AI response.
 */
class ResponseBlockedException extends \RuntimeException {
}
