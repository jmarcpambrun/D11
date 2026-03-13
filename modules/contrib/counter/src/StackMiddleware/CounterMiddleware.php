<?php

declare(strict_types=1);

namespace Drupal\counter\StackMiddleware;

use Drupal\counter\CounterUtility;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class CounterMiddleware implements HttpKernelInterface {

  protected HttpKernelInterface $httpKernel;

  protected CounterUtility $counterUtility;

  public function __construct(
    HttpKernelInterface $http_kernel,
    CounterUtility $counter_utility
  ) {
    $this->httpKernel = $http_kernel;
    $this->counterUtility = $counter_utility;
  }

  /**
   * {@inheritdoc}
   */
  public function handle(
    Request $request,
    $type = HttpKernelInterface::MAIN_REQUEST,
    $catch = TRUE
  ): Response {
    $response = $this->httpKernel->handle($request, $type, $catch);

    if ($response->headers->get('X-Drupal-Cache') === 'HIT') {
      $this->counterUtility->recordCounterData($request);
    }

    return $response;
  }
}
