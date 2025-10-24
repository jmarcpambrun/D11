<?php

namespace Drupal\drd\ContextProvider;

/**
 * Sets the current host as a context on host routes.
 */
class RouteContextHost extends RouteContext {

  /**
   * {@inheritdoc}
   */
  public function getType(): string {
    return 'drd_host';
  }

}
