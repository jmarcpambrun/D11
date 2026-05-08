<?php

namespace Drupal\group\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Sets the current group as a context on group routes.
 */
class GroupRouteContext implements ContextProviderInterface {

  use GroupRouteContextTrait;
  use StringTranslationTrait;

  public function __construct(
    protected RouteMatchInterface $currentRouteMatch,
    protected EntityTypeManagerInterface $entityTypeManager,
    TranslationInterface $string_translation,
  ) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public function getRuntimeContexts(array $unqualified_context_ids) {
    // Create an optional context definition for group entities.
    $context_definition = EntityContextDefinition::fromEntityTypeId('group')
      ->setRequired(FALSE);

    // Cache this context per group on the route.
    $cacheability = new CacheableMetadata();
    $cacheability->setCacheContexts(['route.group']);

    // Create a context from the definition and retrieved or created group.
    $context = new Context($context_definition, $this->getGroupFromRoute());
    $context->addCacheableDependency($cacheability);

    return ['group' => $context];
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableContexts() {
    $context = EntityContext::fromEntityTypeId('group', $this->t('Group from URL'));
    $context->getContextDefinition()->setDescription($this->t('Returns the group from the URL if there is one. E.g.: /group/1/edit will return the group with ID 1. Returns an unsaved group on the group add form.'));
    return ['group' => $context];
  }

}
