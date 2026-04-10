<?php

declare(strict_types=1);

namespace Drupal\tfa\Exceptions;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Access Denied exception when TFA validation is missing.
 */
final class TfaAccessDenied extends AccessDeniedHttpException {
}
