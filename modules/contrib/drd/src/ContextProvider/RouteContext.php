<?php

namespace Drupal\drd\ContextProvider;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\drd\Entity\BaseInterface;

/**
 * Abstract class to set current host/core/domain as a context on routes.
 */
abstract class RouteContext implements ContextProviderInterface, RouteContextInterface {

  use StringTranslationTrait;

  /**
   * Callback to determine if we are on a host, core or domain page.
   *
   * @return RouteContext|null
   *   The matching route context or NULL.
   */
  public static function findDrdContext(): ?RouteContext {
    foreach ([
      'drd_domain.domain_route_context',
      'drd_core.core_route_context',
      'drd_host.host_route_context',
    ] as $item) {
      /** @var RouteContext $context */
      $context = \Drupal::service($item);
      if ($context->getEntity()) {
        return $context;
      }
    }
    return NULL;
  }

  /**
   * The route match object.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * The DRD entity if we are on a DRD entity context.
   *
   * @var \Drupal\drd\Entity\BaseInterface|null
   */
  protected ?BaseInterface $entity = NULL;

  /**
   * Flag for view mode (vs. edit mode)
   *
   * @var bool
   */
  protected bool $viewMode = TRUE;

  /**
   * Constructs a new DrdRouteContext.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match object.
   */
  public function __construct(RouteMatchInterface $route_match) {
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public function getRuntimeContexts(array $unqualified_context_ids): array {
    $result = [];
    $context_definition = EntityContext::fromEntityTypeId($this->getType())->getContextDefinition();
    $value = NULL;
    if (($route_object = $this->routeMatch->getRouteObject()) && ($route_contexts = $route_object->getOption('parameters')) && isset($route_contexts[$this->getType()]) && $domain = $this->routeMatch->getParameter($this->getType())) {
      $value = $domain;
      $compiled = $route_object->compile();
      $tokens = $compiled->getTokens();
      $this->viewMode = (count($tokens) === 2);
    }

    $cacheability = new CacheableMetadata();
    $cacheability->setCacheContexts(['route']);

    // @phpstan-ignore-next-line
    $context = new Context($context_definition, $value);
    $context->addCacheableDependency($cacheability);
    $result[$this->getType()] = $context;

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableContexts(): array {
    $context = EntityContext::fromEntityTypeId($this->getType());
    return [$this->getType() => $context];
  }

  /**
   * Determine the entity of the current context.
   *
   * @return \Drupal\drd\Entity\BaseInterface|bool
   *   The entity if in DRD entity context or FALSE otherwise.
   */
  public function getEntity(): BaseInterface|bool {
    try {
      if ($this->entity === NULL) {
        $runtimeContexts = $this->getRuntimeContexts([]);
        if (!empty($runtimeContexts[$this->getType()])) {
          $this->entity = $runtimeContexts[$this->getType()]->getContextValue();
        }
        else {
          $this->entity = NULL;
        }
      }
    }
    catch (\Exception) {
      $this->entity = NULL;
    }
    return $this->entity ?? FALSE;
  }

  /**
   * Get view mode.
   *
   * @return bool
   *   TRUE if we are in view mode.
   */
  public function getViewMode(): bool {
    return $this->viewMode;
  }

}
